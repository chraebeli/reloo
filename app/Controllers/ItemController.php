<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Activity;
use App\Models\Item;
use App\Models\User;
use App\Services\ImageProcessor;
use App\Services\ItemDeletionService;
use App\Services\Logger;
use App\Services\OpenAIItemSuggestionService;
use Throwable;

final class ItemController extends Controller
{
    private const MAX_FILE_SIZE = 5242880;
    private const MAX_FILES = 6;

    public function index(): void
    {
        $this->requireAuth();
        $model = new Item($this->db);
        $items = $model->searchForUser(
            current_user_id() ?? 0,
            trim($_GET['q'] ?? '') ?: null,
            isset($_GET['group_id']) ? (int) $_GET['group_id'] : null
        );

        $this->view('items/index', ['items' => $items]);
    }

    public function createForm(): void
    {
        $this->requireAuth();
        $itemModel = new Item($this->db);
        $groups = (new \App\Models\Group($this->db))->allForUser(current_user_id() ?? 0);
        $this->view('items/create', [
            'categories' => $itemModel->categories(),
            'groups' => $groups,
            'knownTags' => $itemModel->existingTagsForUser(current_user_id() ?? 0),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        verify_csrf();

        $title = trim($_POST['title'] ?? '');
        if ($title === '' || empty($_POST['group_id'])) {
            Session::flash('error', 'Titel und Gruppe sind Pflichtfelder.');
            $this->redirect('/items/new');
        }

        $itemModel = new Item($this->db);
        $groupId = (int) $_POST['group_id'];

        if (!(new \App\Models\Group($this->db))->isMember(current_user_id() ?? 0, $groupId)) {
            Session::flash('error', 'Ungültige Gruppe oder keine Berechtigung.');
            $this->redirect('/items/new');
        }

        $itemModel->create([
            'group_id' => $groupId,
            'owner_id' => current_user_id(),
            'category_id' => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'title' => $title,
            'description' => trim($_POST['description'] ?? ''),
            'item_condition' => trim($_POST['item_condition'] ?? 'gebraucht_gut'),
            'ownership_type' => trim($_POST['ownership_type'] ?? 'privat_verleihbar'),
            'location_text' => trim($_POST['location_text'] ?? ''),
            'availability_status' => trim($_POST['availability_status'] ?? 'verfügbar'),
            'deposit_note' => trim($_POST['deposit_note'] ?? ''),
            'tags' => trim($_POST['tags'] ?? ''),
            'visibility' => 'group_internal',
        ]);

        $itemId = $itemModel->lastInsertId();
        $this->handleImageUpload($itemModel, $itemId);

        Session::flash('success', 'Gegenstand gespeichert.');
        $this->redirect('/items');
    }

    public function suggestFromImage(): void
    {
        $this->requireAuth();
        verify_csrf();

        $upload = $_FILES['image'] ?? null;
        if (!is_array($upload)) {
            $this->json(['ok' => false, 'message' => 'Kein Bild übertragen.'], 400);
        }

        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $size = (int) ($upload['size'] ?? 0);

        if ($errorCode !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'message' => 'Bild konnte nicht hochgeladen werden.'], 400);
        }
        if (!is_uploaded_file($tmpPath)) {
            $this->json(['ok' => false, 'message' => 'Ungültige Upload-Quelle erkannt.'], 400);
        }
        if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
            $this->json(['ok' => false, 'message' => 'Dateigröße ungültig oder größer als 5 MB.'], 400);
        }

        $processor = new ImageProcessor();

        try {
            $imageInfo = $processor->inspectUploadedImage($tmpPath);
            $optimized = $processor->optimize($tmpPath, $imageInfo['mime']);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'message' => $exception->getMessage()], 400);
        }

        $itemModel = new Item($this->db);
        $suggestionService = new OpenAIItemSuggestionService($this->config);
        $existingCategories = $itemModel->categoryNames();
        $existingTags = $itemModel->existingTagsForUser(current_user_id() ?? 0);

        $dataUrl = 'data:' . $optimized['mime'] . ';base64,' . base64_encode($optimized['binary']);

        try {
            $suggestion = $suggestionService->suggestFromImageDataUrl($dataUrl, $existingCategories, $existingTags);
        } catch (Throwable $exception) {
            Logger::warning('Item suggestion via OpenAI failed', ['error' => $exception->getMessage()]);
            $this->json([
                'ok' => false,
                'message' => 'Die automatische Erkennung war nicht möglich. Du kannst den Gegenstand manuell erfassen.',
            ]);
        }

        if ($suggestion === null) {
            $this->json([
                'ok' => false,
                'message' => $this->suggestionFailureMessage($suggestionService->lastErrorCode()),
            ]);
        }

        $mappedCategory = $this->mapToExistingCategory($suggestion['category'], $itemModel->categories());
        $normalizedTags = $this->normalizeSuggestedTags($suggestion['tags'], $existingTags);

        $this->json([
            'ok' => true,
            'message' => 'Vorschläge aus dem Foto übernommen.',
            'suggestions' => [
                'title' => $suggestion['title'],
                'category_id' => $mappedCategory['id'] ?? null,
                'category_name' => $mappedCategory['name'] ?? $suggestion['category'],
                'description' => $suggestion['description'],
                'tags' => $normalizedTags,
            ],
        ]);
    }

    public function show(): void
    {
        $this->requireAuth();
        $itemId = (int) ($_GET['id'] ?? 0);
        $itemModel = new Item($this->db);
        $item = $itemModel->findForUser($itemId, current_user_id() ?? 0);

        if (!$item) {
            http_response_code(404);
            exit('Gegenstand nicht gefunden.');
        }

        $currentUser = $this->currentUser();
        $deletionService = new ItemDeletionService($this->db, $itemModel, new Activity($this->db));
        $deletionEvaluation = $deletionService->getDeletionEvaluation($currentUser, $item, null);

        $this->view('items/show', [
            'item' => $item,
            'canDeleteItem' => $deletionService->canDeleteItem($currentUser, $item),
            'deleteHint' => $deletionEvaluation['message'] ?? null,
            'requiresAdminReason' => (bool) ($deletionEvaluation['requires_reason'] ?? false),
            'deleteBlockedByState' => $deletionService->blockingStateMessage((int) $item['id']),
        ]);
    }

    public function delete(): void
    {
        $this->requireAuth();
        verify_csrf();

        $itemId = (int) ($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            Session::flash('error', 'Ungültiger Gegenstand.');
            $this->redirect('/items');
        }

        $itemModel = new Item($this->db);
        $item = $itemModel->findDeletionCandidateById($itemId);
        if (!$item) {
            http_response_code(404);
            exit('Gegenstand nicht gefunden.');
        }

        $currentUser = $this->currentUser();
        $deletionService = new ItemDeletionService($this->db, $itemModel, new Activity($this->db));

        try {
            $result = $deletionService->deleteItem($currentUser, $item, trim($_POST['admin_reason'] ?? '') ?: null);
        } catch (Throwable) {
            Session::flash('error', 'Der Gegenstand konnte nicht gelöscht werden.');
            $this->redirect('/items/show?id=' . $itemId);
        }

        if (!$result['allowed']) {
            Session::flash('error', $result['detail'] ?? $result['message'] ?? 'Der Gegenstand konnte nicht gelöscht werden.');
            $this->redirect('/items/show?id=' . $itemId);
        }

        Session::flash('success', $result['message'] ?? 'Der Gegenstand wurde erfolgreich gelöscht.');
        $this->redirect('/items');
    }

    private function currentUser(): array
    {
        $userId = current_user_id() ?? 0;
        $user = (new User($this->db))->findById($userId);

        return $user ?? [
            'id' => $userId,
            'display_name' => (string) ($_SESSION['display_name'] ?? ''),
            'role' => current_user_role(),
        ];
    }

    private function handleImageUpload(Item $itemModel, int $itemId): void
    {
        if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
            return;
        }

        $uploadDir = __DIR__ . '/../../public/uploads/items';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $errors = [];
        $processed = 0;
        $processor = new ImageProcessor();

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpPath) {
            if ($processed >= self::MAX_FILES) {
                break;
            }

            $errorCode = (int) ($_FILES['images']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode !== UPLOAD_ERR_OK) {
                $errors[] = 'Eine Datei konnte nicht hochgeladen werden.';
                continue;
            }

            $tmpPath = (string) $tmpPath;
            if (!is_uploaded_file($tmpPath)) {
                $errors[] = 'Ungültige Upload-Quelle erkannt.';
                continue;
            }

            $size = (int) ($_FILES['images']['size'][$index] ?? 0);
            if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
                $errors[] = 'Eine Datei überschreitet das 5-MB-Limit.';
                continue;
            }

            try {
                $imageInfo = $processor->inspectUploadedImage($tmpPath);
                $optimized = $processor->optimize($tmpPath, $imageInfo['mime']);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
                continue;
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $optimized['extension'];
            $target = $uploadDir . '/' . $filename;
            if (file_put_contents($target, $optimized['binary'], LOCK_EX) === false) {
                $errors[] = 'Bild konnte nicht gespeichert werden.';
                continue;
            }

            $itemModel->addImage($itemId, 'uploads/items/' . $filename);
            $processed++;
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', array_unique($errors)));
        }
    }

    /** @param list<array{id:int,name:string}> $categories */
    private function mapToExistingCategory(string $suggestedCategory, array $categories): ?array
    {
        $needle = $this->normalizeTerm($suggestedCategory);
        if ($needle === '') {
            return null;
        }

        foreach ($categories as $category) {
            if ($needle === $this->normalizeTerm((string) $category['name'])) {
                return ['id' => (int) $category['id'], 'name' => (string) $category['name']];
            }
        }

        return null;
    }

    /**
     * @param list<string> $suggestedTags
     * @param list<string> $existingTags
     * @return list<string>
     */
    private function normalizeSuggestedTags(array $suggestedTags, array $existingTags): array
    {
        $existingLookup = [];
        foreach ($existingTags as $tag) {
            $normalized = $this->normalizeTerm($tag);
            if ($normalized !== '' && !isset($existingLookup[$normalized])) {
                $existingLookup[$normalized] = $tag;
            }
        }

        $result = [];
        $seen = [];

        foreach ($suggestedTags as $rawTag) {
            $tag = trim((string) $rawTag);
            if ($tag === '') {
                continue;
            }

            $normalized = $this->normalizeTerm($tag);
            if ($normalized === '') {
                continue;
            }

            $mapped = $existingLookup[$normalized] ?? $this->findClosestExistingTag($tag, $existingTags);
            $finalTag = $mapped ?? $this->toTitleCase($tag);
            $finalKey = $this->normalizeTerm($finalTag);

            if ($finalKey === '' || isset($seen[$finalKey])) {
                continue;
            }

            $seen[$finalKey] = true;
            $result[] = $finalTag;
        }

        return $result;
    }

    /** @param list<string> $existingTags */
    private function findClosestExistingTag(string $tag, array $existingTags): ?string
    {
        $needle = $this->normalizeTerm($tag);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($existingTags as $existing) {
            $candidate = $this->normalizeTerm($existing);
            if ($candidate === '') {
                continue;
            }

            if (str_contains($candidate, $needle) || str_contains($needle, $candidate)) {
                return $existing;
            }

            similar_text($needle, $candidate, $percent);
            if ($percent > $bestScore) {
                $bestScore = $percent;
                $best = $existing;
            }

            $distance = levenshtein($needle, $candidate);
            $maxLength = max(strlen($needle), strlen($candidate));
            if ($maxLength > 0) {
                $distanceScore = (1 - ($distance / $maxLength)) * 100;
                if ($distanceScore > $bestScore) {
                    $bestScore = $distanceScore;
                    $best = $existing;
                }
            }
        }

        return $bestScore >= 72.0 ? $best : null;
    }

    private function normalizeTerm(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';

        return $value;
    }

    private function toTitleCase(string $value): string
    {
        return mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }



    private function suggestionFailureMessage(?string $errorCode): string
    {
        return match ($errorCode) {
            'missing_api_key' => 'Die automatische Erkennung ist noch nicht eingerichtet (OpenAI API Key fehlt). Du kannst den Gegenstand manuell erfassen.',
            'insufficient_quota' => 'Das KI-Kontingent ist aufgebraucht. Bitte API-Abrechnung/Kontingent prüfen. Du kannst den Gegenstand manuell erfassen.',
            'rate_limited' => 'Der KI-Dienst ist gerade ausgelastet. Bitte in wenigen Sekunden erneut versuchen oder manuell erfassen.',
            'auth_error' => 'Die KI-Konfiguration ist ungültig (Authentifizierung fehlgeschlagen). Du kannst den Gegenstand manuell erfassen.',
            default => 'Die automatische Erkennung war nicht möglich. Du kannst den Gegenstand manuell erfassen.',
        };
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

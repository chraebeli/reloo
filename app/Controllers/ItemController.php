<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Item;

final class ItemController extends Controller
{
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
        $this->view('items/create', ['categories' => $itemModel->categories(), 'groups' => $groups]);
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

    public function show(): void
    {
        $this->requireAuth();
        $itemId = (int) ($_GET['id'] ?? 0);
        $item = (new Item($this->db))->findForUser($itemId, current_user_id() ?? 0);

        if (!$item) {
            http_response_code(404);
            exit('Gegenstand nicht gefunden.');
        }

        $this->view('items/show', ['item' => $item]);
    }

    private function handleImageUpload(Item $itemModel, int $itemId): void
    {
        if (!isset($_FILES['images']) || !is_array($_FILES['images']['name'])) {
            return;
        }

        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $maxFileSize = 5 * 1024 * 1024;
        $maxFiles = 6;
        $uploadDir = __DIR__ . '/../../uploads/items';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $errors = [];
        $processed = 0;

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpPath) {
            if ($processed >= $maxFiles) {
                break;
            }

            if (($_FILES['images']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'Eine Datei konnte nicht hochgeladen werden.';
                continue;
            }
            if (!is_uploaded_file($tmpPath)) {
                $errors[] = 'Ungültige Upload-Quelle erkannt.';
                continue;
            }

            $mime = mime_content_type($tmpPath);
            if (!isset($allowedMimes[$mime])) {
                $errors[] = 'Nur JPG, PNG und WEBP sind erlaubt.';
                continue;
            }

            $size = filesize($tmpPath);
            if ($size === false || $size > $maxFileSize) {
                $errors[] = 'Eine Datei überschreitet das 5-MB-Limit.';
                continue;
            }

            $imgInfo = @getimagesize($tmpPath);
            if (!is_array($imgInfo) || ($imgInfo[0] ?? 0) < 80 || ($imgInfo[1] ?? 0) < 80) {
                $errors[] = 'Bildauflösung zu klein (mind. 80x80 Pixel).';
                continue;
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
            $target = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($tmpPath, $target)) {
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
}

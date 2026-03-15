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
        $itemModel->create([
            'group_id' => (int) $_POST['group_id'],
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
        $uploadDir = __DIR__ . '/../../uploads/items';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $index => $tmpPath) {
            if (($_FILES['images']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            if (!is_uploaded_file($tmpPath)) {
                continue;
            }

            $mime = mime_content_type($tmpPath);
            if (!isset($allowedMimes[$mime])) {
                continue;
            }

            if (filesize($tmpPath) > 5 * 1024 * 1024) {
                continue;
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimes[$mime];
            $target = $uploadDir . '/' . $filename;
            move_uploaded_file($tmpPath, $target);
            $itemModel->addImage($itemId, 'uploads/items/' . $filename);
        }
    }
}

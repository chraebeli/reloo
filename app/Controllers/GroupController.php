<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Models\Group;

final class GroupController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $model = new Group($this->db);
        $this->view('groups/index', ['groups' => $model->allForUser(current_user_id() ?? 0)]);
    }

    public function create(): void
    {
        $this->requireAuth();
        verify_csrf();

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Session::flash('error', 'Gruppenname ist erforderlich.');
            $this->redirect('/groups');
        }

        $model = new Group($this->db);
        $inviteCode = strtoupper(bin2hex(random_bytes(4)));
        $model->create([
            'name' => $name,
            'description' => trim($_POST['description'] ?? ''),
            'invite_code' => $inviteCode,
            'created_by' => current_user_id(),
        ]);

        $model->addCreatorAsAdmin($model->lastInsertId(), current_user_id() ?? 0);

        Session::flash('success', 'Gruppe erstellt. Einladungscode: ' . $inviteCode);
        $this->redirect('/groups');
    }

    public function join(): void
    {
        $this->requireAuth();
        verify_csrf();
        $code = strtoupper(trim($_POST['invite_code'] ?? ''));
        $model = new Group($this->db);

        if (!$model->joinByInviteCode(current_user_id() ?? 0, $code)) {
            Session::flash('error', 'Einladungscode ungültig oder Beitritt nicht möglich.');
            $this->redirect('/groups');
        }

        Session::flash('success', 'Erfolgreich beigetreten.');
        $this->redirect('/groups');
    }
}

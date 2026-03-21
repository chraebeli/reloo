<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Item;
use PDO;
use RuntimeException;

final class ItemDeletionService
{
    private const ADMIN_REASON_MIN_LENGTH = 10;

    public function __construct(
        private PDO $db,
        private Item $itemModel,
        private Activity $activityModel,
    ) {
    }

    public function getDeletionEvaluation(array $user, array $item, ?string $adminReason = null): array
    {
        $isAdmin = ($user['role'] ?? 'member') === 'admin';
        $isOwner = (int) ($user['id'] ?? 0) === (int) ($item['owner_id'] ?? 0);
        $isCommunal = ($item['ownership_type'] ?? '') === 'gemeinschaftlich';

        if (!empty($item['deleted_at'])) {
            return [
                'allowed' => false,
                'message' => 'Der Gegenstand wurde bereits gelöscht.',
                'requires_reason' => false,
            ];
        }

        if ($isAdmin) {
            $reason = trim((string) $adminReason);
            if ($reason === '') {
                return [
                    'allowed' => false,
                    'message' => 'Für die administrative Löschung ist eine Begründung erforderlich.',
                    'requires_reason' => true,
                ];
            }

            if (mb_strlen($reason) < self::ADMIN_REASON_MIN_LENGTH) {
                return [
                    'allowed' => false,
                    'message' => 'Die Begründung für die administrative Löschung muss mindestens 10 Zeichen lang sein.',
                    'requires_reason' => true,
                ];
            }

            return [
                'allowed' => true,
                'message' => null,
                'requires_reason' => true,
                'admin_reason' => $reason,
            ];
        }

        if ($isCommunal) {
            return [
                'allowed' => false,
                'message' => 'Gemeinschaftliche Gegenstände können nur durch Administratoren gelöscht werden.',
                'requires_reason' => false,
            ];
        }

        if (!$isOwner) {
            return [
                'allowed' => false,
                'message' => 'Du darfst diesen Gegenstand nicht löschen.',
                'requires_reason' => false,
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
            'requires_reason' => false,
        ];
    }

    public function canDeleteItem(array $user, array $item): bool
    {
        if (!empty($item['deleted_at'])) {
            return false;
        }

        if (($user['role'] ?? 'member') === 'admin') {
            return true;
        }

        return (int) ($user['id'] ?? 0) === (int) ($item['owner_id'] ?? 0)
            && ($item['ownership_type'] ?? '') !== 'gemeinschaftlich';
    }

    public function blockingStateMessage(int $itemId): ?string
    {
        $blockingState = $this->itemModel->findBlockingState($itemId);
        if ($blockingState === null) {
            return null;
        }

        return match ($blockingState) {
            'loan' => 'Der Gegenstand kann aktuell nicht gelöscht werden, da noch aktive Ausleihen bestehen.',
            'request' => 'Der Gegenstand kann aktuell nicht gelöscht werden, da noch offene Anfragen oder Reservierungen bestehen.',
            'repair' => 'Der Gegenstand kann aktuell nicht gelöscht werden, da noch eine laufende Reparatur besteht.',
            default => 'Der Gegenstand kann aktuell nicht gelöscht werden, da noch aktive Vorgänge bestehen.',
        };
    }

    public function deleteItem(array $user, array $item, ?string $adminReason = null): array
    {
        $evaluation = $this->getDeletionEvaluation($user, $item, $adminReason);
        if (!$evaluation['allowed']) {
            return $evaluation;
        }

        $blockingMessage = $this->blockingStateMessage((int) $item['id']);
        if ($blockingMessage !== null) {
            return [
                'allowed' => false,
                'message' => 'Der Gegenstand kann aktuell nicht gelöscht werden, da noch aktive Vorgänge bestehen.',
                'detail' => $blockingMessage,
                'requires_reason' => (bool) ($evaluation['requires_reason'] ?? false),
            ];
        }

        $this->db->beginTransaction();

        try {
            $reason = $evaluation['admin_reason'] ?? null;
            $deletedByRole = (string) ($user['role'] ?? 'member');

            $this->itemModel->softDelete(
                (int) $item['id'],
                (int) $user['id'],
                $deletedByRole,
                $reason
            );

            $this->itemModel->logDeletion([
                'item_id' => (int) $item['id'],
                'item_title' => (string) $item['title'],
                'ownership_type' => (string) $item['ownership_type'],
                'deleted_by' => (int) $user['id'],
                'deleted_by_role' => $deletedByRole,
                'owner_id' => (int) $item['owner_id'],
                'admin_reason' => $reason,
            ]);

            if ($deletedByRole === 'admin') {
                $this->activityModel->log(
                    (int) $user['id'],
                    (int) $item['group_id'],
                    'item_deleted_admin',
                    sprintf(
                        'Admin entfernte Gegenstand #%d „%s“ von %s. Grund: %s',
                        (int) $item['id'],
                        mb_substr((string) $item['title'], 0, 70),
                        (string) ($item['owner_name'] ?? 'Unbekannt'),
                        mb_substr((string) $reason, 0, 120)
                    )
                );
            } else {
                $this->activityModel->log(
                    (int) $user['id'],
                    (int) $item['group_id'],
                    'item_deleted_owner',
                    sprintf(
                        'Gegenstand #%d „%s“ wurde vom Eigentümer gelöscht.',
                        (int) $item['id'],
                        mb_substr((string) $item['title'], 0, 90)
                    )
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw new RuntimeException('Das Löschen des Gegenstands ist fehlgeschlagen.', 0, $exception);
        }

        return [
            'allowed' => true,
            'message' => $deletedByRole === 'admin'
                ? 'Der Gegenstand wurde durch einen Administrator entfernt.'
                : 'Der Gegenstand wurde erfolgreich gelöscht.',
            'requires_reason' => (bool) ($evaluation['requires_reason'] ?? false),
        ];
    }
}

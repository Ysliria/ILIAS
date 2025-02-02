<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * Deal with ilias rbac-system
 */
class ilIndividualAssessmentAccessHandler implements IndividualAssessmentAccessHandler
{
    public const DEFAULT_ROLE = 'il_iass_member';

    protected ilObjIndividualAssessment $iass;
    protected ilAccessHandler $handler;
    protected ilRbacAdmin $admin;
    protected ilRbacReview $review;
    protected ilObjUser $usr;

    public function __construct(
        ilObjIndividualAssessment $iass,
        ilAccessHandler $handler,
        ilRbacAdmin $admin,
        ilRbacReview $review,
        ilObjUser $usr
    ) {
        $this->iass = $iass;
        $this->handler = $handler;
        $this->admin = $admin;
        $this->review = $review;
        $this->usr = $usr;
    }

    /**
     * @inheritdoc
     */
    public function checkRBACAccessToObj(string $operation): bool
    {
        if($this->simulateMember()) {
            return $this->checkMemberRoleForPermission($operation);
        } else {
            return $this->isSystemAdmin() ||
                $this->handler->checkAccessOfUser($this->usr->getId(), $operation, '', $this->iass->getRefId(), 'iass');
        }
    }

    public function checkRBACOrPositionAccessToObj(string $operation)
    {
        if ($this->isSystemAdmin()) {
            return true;
        }

        if ($operation == "read_learning_progress") {
            return $this->handler->checkRbacOrPositionPermissionAccess(
                "read_learning_progress",
                "read_learning_progress",
                $this->iass->getRefId()
            );
        }

        if ($operation == "write_learning_progress") {
            return $this->handler->checkRbacOrPositionPermissionAccess(
                // This feels super odd, but this is actually ok because we do not have
                // a dedicated RBAC permission to write_learning_progress.
                // See: https://mantis.ilias.de/view.php?id=36056#c89865
                "read_learning_progress",
                "write_learning_progress",
                $this->iass->getRefId()
            );
        }

        throw new \LogicException("Unknown rbac/position-operation: $operation");
    }

    public function simulateMember(): bool
    {
        $settings = ilMemberViewSettings::getInstance();
        return $settings->isActive() &&
            $settings->getContainer() === $this->iass->getParentContainerIdByType($this->iass->getRefId(), ['crs']);
    }

    protected function checkMemberRoleForPermission(string $operation): bool
    {
        $ref_id = $this->iass->getRefId();
        $roles = array_filter(
            $this->review->getParentRoleIds($ref_id),
            static fn(array $role): bool => str_starts_with($role['title'], 'il_crs_member_')
        );
        if($roles === []) {
            return false;
        }
        $role = array_shift($roles);
        $active_ops = $this->review->getActiveOperationsOfRole($ref_id, $role['rol_id']);
        foreach($active_ops as $op) {
            if($this->review->getOperation($op)['operation'] === $operation) {
                return true;
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function initDefaultRolesForObject(ilObjIndividualAssessment $iass): void
    {
        ilObjRole::createDefaultRole(
            $this->getRoleTitleByObj($iass),
            "Admin of iass obj_no." . $iass->getId(),
            self::DEFAULT_ROLE,
            $iass->getRefId()
        );
    }

    /**
     * @inheritdoc
     */
    public function assignUserToMemberRole(ilObjUser $usr, ilObjIndividualAssessment $iass): bool
    {
        $this->admin->assignUser($this->getMemberRoleIdForObj($iass), $usr->getId());
        return true;
    }

    /**
     * @inheritdoc
     */
    public function deassignUserFromMemberRole(ilObjUser $usr, ilObjIndividualAssessment $iass): bool
    {
        $this->admin->deassignUser($this->getMemberRoleIdForObj($iass), $usr->getId());
        return true;
    }

    protected function getRoleTitleByObj(ilObjIndividualAssessment $iass): string
    {
        return self::DEFAULT_ROLE . '_' . $iass->getRefId();
    }

    /**
     * @return false|mixed
     */
    protected function getMemberRoleIdForObj(ilObjIndividualAssessment $iass)
    {
        return current($this->review->getLocalRoles($iass->getRefId()));
    }

    public function mayReadObject(): bool
    {
        return $this->checkRBACAccessToObj('read');
    }

    public function mayEditObject(): bool
    {
        return $this->checkRBACAccessToObj('write');
    }

    public function mayEditPermissions(): bool
    {
        return $this->checkRBACAccessToObj('edit_permission');
    }

    public function mayEditMembers(): bool
    {
        return $this->checkRBACAccessToObj('edit_members');
    }

    public function mayViewAnyUser(): bool
    {
        return $this->checkRBACOrPositionAccessToObj('read_learning_progress');
    }

    public function mayViewAllUsers(): bool
    {
        return $this->checkRBACAccessToObj('read_learning_progress');
    }

    public function mayGradeAnyUser(): bool
    {
        return $this->checkRBACOrPositionAccessToObj('write_learning_progress');
    }

    public function mayGradeUser(int $user_id): bool
    {
        return
            (count(
                $this->handler->filterUserIdsByRbacOrPositionOfCurrentUser(
                    // This feels super odd, but this is actually ok because we do not have
                    // a dedicated RBAC permission to write_learning_progress.
                    // See: https://mantis.ilias.de/view.php?id=36056#c89865
                    "read_learning_progress",
                    "write_learning_progress",
                    $this->iass->getRefId(),
                    [$user_id]
                )
            ) > 0);
    }

    public function mayViewUser(int $user_id): bool
    {
        return
            $this->mayViewAllUsers() ||
            (count(
                $this->handler->filterUserIdsByRbacOrPositionOfCurrentUser(
                    "read_learning_progress",
                    "read_learning_progress",
                    $this->iass->getRefId(),
                    [$user_id]
                )
            ) > 0);
    }

    public function mayAmendAllUsers(): bool
    {
        return $this->checkRBACAccessToObj('amend_grading');
    }

    public function isSystemAdmin(): bool
    {
        return $this->review->isAssigned($this->usr->getId(), SYSTEM_ROLE_ID);
    }

    public function mayEditLearningProgressSettings(): bool
    {
        return $this->checkRBACAccessToObj('edit_learning_progress');
    }
}

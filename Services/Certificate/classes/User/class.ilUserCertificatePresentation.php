<?php

declare(strict_types=1);

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

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilUserCertificatePresentation
{
    public function __construct(
        private readonly int $objId,
        private readonly string $objType,
        private readonly ?ilUserCertificate $userCertificate,
        private readonly string $objectTitle,
        private readonly string $objectDescription,
        private readonly string $userName = ''
    ) {
    }

    public function getObjId(): int
    {
        return $this->objId;
    }

    public function getObjType(): string
    {
        return $this->objType;
    }

    public function getUserCertificate(): ?ilUserCertificate
    {
        return $this->userCertificate;
    }

    public function getObjectTitle(): string
    {
        return $this->objectTitle;
    }

    public function getObjectDescription(): string
    {
        return $this->objectDescription;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }
}

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

namespace ILIAS\MetaData\Copyright;

use ILIAS\Data\URI;

class NullCopyrightData implements CopyrightDataInterface
{
    public function fullName(): string
    {
        return '';
    }

    public function link(): ?URI
    {
        return null;
    }

    public function hasImage(): bool
    {
        return false;
    }

    public function isImageLink(): bool
    {
        return false;
    }

    public function imageLink(): ?URI
    {
        return null;
    }

    public function imageFile(): string
    {
        return '';
    }

    public function altText(): string
    {
        return '';
    }

    public function fallBackToDefaultImage(): bool
    {
        return false;
    }
}

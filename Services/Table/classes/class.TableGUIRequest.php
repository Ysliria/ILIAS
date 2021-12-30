<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 */

namespace ILIAS\Table;

use ILIAS\Repository;

class TableGUIRequest
{
    use Repository\BaseGUIRequest;

    public function __construct(
        \ILIAS\HTTP\Services $http,
        \ILIAS\Refinery\Factory $refinery,
        ?array $passed_query_params = null,
        ?array $passed_post_data = null
    ) {
        $this->initRequest(
            $http,
            $refinery,
            $passed_query_params,
            $passed_post_data
        );
    }

    public function getExportMode($prefix) : bool
    {
        return (bool) $this->int($prefix . "_xpt");
    }

    public function getTemplate($prefix) : string
    {
        return $this->str($prefix . "_tpl");
    }

    public function getRows($prefix) : int
    {
        return $this->int($prefix . "_trows");
    }

    public function getPostVar() : string
    {
        return $this->str("postvar");
    }

    public function getNavPar(string $np, $nr = 0) : string
    {
        if ($nr > 0) {
            $np .= (string) $nr;
        }
        return $this->str($np);
    }

    public function getFF($id) : array
    {
        return $this->strArray("tblff" . $id);
    }

    public function getFS($id) : array
    {
        return $this->strArray("tblfs" . $id);
    }

    public function getFSH($id) : bool
    {
        return (bool) $this->int("tblfsh" . $id);
    }

    public function getFSF($id) : bool
    {
        return (bool) $this->int("tblfsf" . $id);
    }

    public function getTemplCreate() : string
    {
        return $this->str("tbltplcrt");
    }

    public function getTemplDelete() : string
    {
        return $this->str("tbltpldel");
    }

    public function getTableId() : string
    {
        return $this->str("table_id");
    }

    public function getUserId() : int
    {
        return $this->int("user_id");
    }
}

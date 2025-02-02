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

/**
 * TableGUI class for (broken) links in learning module
 *
 * @author Alexander Killing <killing@leifos.de>
 */
class ilLinksTableGUI extends ilTable2GUI
{
    protected string $lm_type;
    protected int $lm_id;

    public function __construct(
        object $a_parent_obj,
        string $a_parent_cmd,
        int $a_lm_id,
        string $a_lm_type
    ) {
        global $DIC;

        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $ilCtrl = $DIC->ctrl();
        $lng = $DIC->language();

        parent::__construct($a_parent_obj, $a_parent_cmd);

        $this->addColumn($lng->txt("pg"), "", "");
        $this->addColumn($lng->txt("cont_internal_links"), "", "");
        $this->setEnableHeader(true);
        $this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
        $this->setRowTemplate(
            "tpl.links_table_row.html",
            "Modules/LearningModule"
        );
        $this->lm_id = $a_lm_id;
        $this->lm_type = $a_lm_type;
        $this->getLinks();

        $this->setTitle($lng->txt("cont_internal_links"));
    }

    public function getLinks(): void
    {
        $pages = ilLMPageObject::getPagesWithLinksList($this->lm_id, $this->lm_type);
        $this->setData($pages);
    }

    protected function fillRow(array $a_set): void
    {
        $lng = $this->lng;
        $ilCtrl = $this->ctrl;

        $this->tpl->setVariable("TXT_PAGE_TITLE", $a_set["title"]);
        $ilCtrl->setParameterByClass(
            "illmpageobjectgui",
            "obj_id",
            $a_set["obj_id"]
        );
        $this->tpl->setVariable(
            "HREF_PAGE",
            $ilCtrl->getLinkTargetByClass("illmpageobjectgui", "edit")
        );

        $page_object = new ilLMPage($a_set["obj_id"]);
        $page_object->buildDom();
        $int_links = $page_object->getInternalLinks();

        foreach ($int_links as $link) {
            $target = $link["Target"];
            if (substr($target, 0, 4) == "il__") {
                $target_arr = explode("_", $target);
                $target_id = $target_arr[count($target_arr) - 1];
                $type = $link["Type"];

                switch ($type) {
                    case "PageObject":
                        $this->tpl->setCurrentBlock("link");
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("pg"));
                        if (ilLMObject::_exists($target_id)) {
                            $lm_id = ilLMObject::_lookupContObjID($target_id);
                            $add_str = ($lm_id != $this->lm_id)
                                ? " (" . ilObject::_lookupTitle($lm_id) . ")"
                                : "";
                            $this->tpl->setVariable(
                                "TXT_LINK_TITLE",
                                ilLMObject::_lookupTitle($target_id) . $add_str
                            );
                        } else {
                            $this->tpl->setVariable(
                                "TXT_MISSING",
                                "<strong>" . $lng->txt("cont_target_missing") . " [" . $target_id . "]" . "</strong>"
                            );
                        }
                        $this->tpl->parseCurrentBlock();
                        break;

                    case "StructureObject":
                        $this->tpl->setCurrentBlock("link");
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("st"));
                        if (ilLMObject::_exists($target_id)) {
                            $lm_id = ilLMObject::_lookupContObjID($target_id);
                            $add_str = ($lm_id != $this->lm_id)
                                ? " (" . ilObject::_lookupTitle($lm_id) . ")"
                                : "";
                            $this->tpl->setVariable(
                                "TXT_LINK_TITLE",
                                ilLMObject::_lookupTitle($target_id) . $add_str
                            );
                        } else {
                            $this->tpl->setVariable(
                                "TXT_MISSING",
                                "<strong>" . $lng->txt("cont_target_missing") . " [" . $target_id . "]" . "</strong>"
                            );
                        }
                        $this->tpl->parseCurrentBlock();
                        break;

                    case "GlossaryItem":
                        $this->tpl->setCurrentBlock("link");
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("cont_term"));
                        if (ilGlossaryTerm::_exists($target_id)) {
                            $this->tpl->setVariable(
                                "TXT_LINK_TITLE",
                                ilGlossaryTerm::_lookGlossaryTerm($target_id)
                            );
                        } else {
                            $this->tpl->setVariable(
                                "TXT_MISSING",
                                "<strong>" . $lng->txt("cont_target_missing") . " [" . $target_id . "]" . "</strong>"
                            );
                        }
                        $this->tpl->parseCurrentBlock();
                        break;

                    case "MediaObject":
                        $this->tpl->setCurrentBlock("link");
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("mob"));
                        if (ilObject::_exists($target_id)) {
                            $this->tpl->setVariable(
                                "TXT_LINK_TITLE",
                                ilObject::_lookupTitle($target_id)
                            );
                        } else {
                            $this->tpl->setVariable(
                                "TXT_MISSING",
                                "<strong>" . $lng->txt("cont_target_missing") . " [" . $target_id . "]" . "</strong>"
                            );
                        }
                        $this->tpl->parseCurrentBlock();
                        break;

                    case "RepositoryItem":
                        $this->tpl->setCurrentBlock("link");
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("cont_repository_item"));
                        $obj_type = ilObject::_lookupType($target_id, true);
                        $obj_id = ilObject::_lookupObjId($target_id);
                        if (ilObject::_exists($obj_id)) {
                            $this->tpl->setVariable(
                                "TXT_LINK_TITLE",
                                ilObject::_lookupTitle($obj_id) . " (" .
                                $lng->txt(("obj_" . $obj_type))
                                . ")"
                            );
                        } else {
                            $this->tpl->setVariable(
                                "TXT_MISSING",
                                "<strong>" . $lng->txt("cont_target_missing") . " [" . $target_id . "]" . "</strong>"
                            );
                        }
                        $this->tpl->parseCurrentBlock();
                        break;
                }
            } else {
                $type = $link["Type"];

                switch ($type) {
                    case "PageObject":
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("pg"));
                        break;
                    case "StructureObject":
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("st"));
                        break;
                    case "GlossaryItem":
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("cont_term"));
                        break;
                    case "MediaObject":
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("mob"));
                        break;
                    case "RepositoryItem":
                        $this->tpl->setVariable("TXT_LINK_TYPE", $lng->txt("cont_repository_item"));
                        break;
                }

                $this->tpl->setCurrentBlock("link");
                $this->tpl->setVariable(
                    "TXT_MISSING",
                    "<strong>" . $lng->txt("cont_target_missing") . " [" . $target . "]" . "</strong>"
                );
                $this->tpl->parseCurrentBlock();
            }
        }
    }
}

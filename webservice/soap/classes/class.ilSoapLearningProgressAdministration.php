<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once './webservice/soap/classes/class.ilSoapAdministration.php';

/**
 * This class handles all DB changes necessary for fraunhofer
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 * @version $Id$
 */
class ilSoapLearningProgressAdministration extends ilSoapAdministration
{
    /** @var string[] */
    protected static array $DELETE_PROGRESS_FILTER_TYPES = ['sahs', 'tst'];

    public const PROGRESS_FILTER_ALL = 0;
    public const PROGRESS_FILTER_IN_PROGRESS = 1;
    public const PROGRESS_FILTER_COMPLETED = 2;
    public const PROGRESS_FILTER_FAILED = 3;
    public const PROGRESS_FILTER_NOT_ATTEMPTED = 4;

    public const SOAP_LP_ERROR_AUTHENTICATION = 50;
    public const SOAP_LP_ERROR_INVALID_FILTER = 52;
    public const SOAP_LP_ERROR_INVALID_REF_ID = 54;
    public const SOAP_LP_ERROR_LP_NOT_AVAILABLE = 56;
    public const SOAP_LP_ERROR_NO_PERMISSION = 58;
    public const SOAP_LP_ERROR_LP_NOT_ENABLED = 60;

    /** @var int[] */
    protected static array $PROGRESS_INFO_TYPES = [
        self::PROGRESS_FILTER_ALL,
        self::PROGRESS_FILTER_IN_PROGRESS,
        self::PROGRESS_FILTER_COMPLETED,
        self::PROGRESS_FILTER_FAILED,
        self::PROGRESS_FILTER_NOT_ATTEMPTED
    ];

    public const USER_FILTER_ALL = -1;

    /**
     * @return bool|soap_fault|SoapFault|null
     */
    public function deleteProgress(string $sid, array $ref_ids, array $usr_ids, array $type_filter, array $progress_filter)
    {
        $this->initAuth($sid);
        $this->initIlias();

        if (!is_array($usr_ids)) {
            $usr_ids = (array) $usr_ids;
        }
        if (!is_array($type_filter)) {
            $type_filter = (array) $type_filter;
        }

        if (!$this->checkSession($sid)) {
            return $this->raiseError($this->getMessage(), $this->getMessageCode());
        }

        if (array_diff($type_filter, self::$DELETE_PROGRESS_FILTER_TYPES)) {
            return $this->raiseError('Invalid filter type given', 'Client');
        }

        include_once 'Services/User/classes/class.ilObjUser.php';
        if (!in_array(self::USER_FILTER_ALL, $usr_ids) && !ilObjUser::userExists($usr_ids)) {
            return $this->raiseError('Invalid user ids given', 'Client');
        }

        $valid_refs = array();
        $type = '';
        foreach ($ref_ids as $ref_id) {
            $obj_id = ilObject::_lookupObjId($ref_id);
            $type = ilObject::_lookupType($obj_id);

            // All containers
            if ($GLOBALS['DIC']['objDefinition']->isContainer($type)) {
                $all_sub_objs = array();
                foreach (($type_filter) as $type_filter_item) {
                    $sub_objs = $GLOBALS['DIC']['tree']->getSubTree(
                        $GLOBALS['DIC']['tree']->getNodeData($ref_id),
                        false,
                        $type_filter_item
                    );
                    $all_sub_objs = array_merge($all_sub_objs, $sub_objs);
                }

                foreach ($all_sub_objs as $child_ref) {
                    $child_type = ilObject::_lookupType(ilObject::_lookupObjId($child_ref));
                    if (!$GLOBALS['DIC']['ilAccess']->checkAccess('write', '', $child_ref)) {
                        return $this->raiseError(
                            'Permission denied for : ' . $ref_id . ' -> type ' . $type,
                            'Client'
                        );
                    }
                    $valid_refs[] = $child_ref;
                }
            } elseif (in_array($type, $type_filter)) {
                if (!$GLOBALS['DIC']['ilAccess']->checkAccess('write', '', $ref_id)) {
                    return $this->raiseError('Permission denied for : ' . $ref_id . ' -> type ' . $type, 'Client');
                }
                $valid_refs[] = $ref_id;
            } else {
                return $this->raiseError(
                    'Invalid object type given for : ' . $ref_id . ' -> type ' . $type,
                    'Client'
                );
            }
        }

        // Delete tracking data
        foreach ($valid_refs as $ref_id) {
            include_once './Services/Object/classes/class.ilObjectFactory.php';
            $obj = ilObjectFactory::getInstanceByRefId($ref_id, false);

            if (!$obj instanceof ilObject) {
                return $this->raiseError('Invalid reference id given : ' . $ref_id . ' -> type ' . $type, 'Client');
            }

            // filter users
            $valid_users = $this->applyProgressFilter($obj->getId(), $usr_ids, $progress_filter);

            switch ($obj->getType()) {
                case 'sahs':
                    include_once './Modules/ScormAicc/classes/class.ilObjSAHSLearningModule.php';
                    $subtype = ilObjSAHSLearningModule::_lookupSubType($obj->getId());

                    switch ($subtype) {
                        case 'scorm':
                            $this->deleteScormTracking($obj->getId(), $valid_users);
                            break;

                        case 'scorm2004':
                            $this->deleteScorm2004Tracking($obj->getId(), $valid_users);
                            break;
                    }
                    break;

                case 'tst':

                    /** @var $obj ilObjTest */
                    $obj->removeTestResultsFromSoapLpAdministration(array_values($valid_users));
                    break;
            }

            // Refresh status
            include_once './Services/Tracking/classes/class.ilLPStatusWrapper.php';
            ilLPStatusWrapper::_resetInfoCaches($obj->getId());
            ilLPStatusWrapper::_refreshStatus($obj->getId(), $valid_users);
        }
        return true;
    }

    /**
     * @param int[]  $a_progress_filter
     * @return soap_fault|SoapFault|string
     */
    public function getProgressInfo(string $sid, int $a_ref_id, array $a_progress_filter)
    {
        global $DIC;

        $this->initAuth($sid);
        $this->initIlias();

        $ilAccess = $DIC->access();

        // Check session
        if (!$this->checkSession($sid)) {
            return $this->raiseError(
                'Error ' . self::SOAP_LP_ERROR_AUTHENTICATION . ':' . $this->getMessage(),
                self::SOAP_LP_ERROR_AUTHENTICATION
            );
        }

        // Check filter
        if (array_diff($a_progress_filter, self::$PROGRESS_INFO_TYPES)) {
            return $this->raiseError(
                'Error ' . self::SOAP_LP_ERROR_INVALID_FILTER . ': Invalid filter type given',
                self::SOAP_LP_ERROR_INVALID_FILTER
            );
        }
        // Check LP enabled
        include_once("Services/Tracking/classes/class.ilObjUserTracking.php");
        if (!ilObjUserTracking::_enabledLearningProgress()) {
            return $this->raiseError(
                'Error ' . self::SOAP_LP_ERROR_LP_NOT_ENABLED . ': Learning progress not enabled in ILIAS',
                self::SOAP_LP_ERROR_LP_NOT_ENABLED
            );
        }

        include_once './Services/Object/classes/class.ilObjectFactory.php';
        $obj = ilObjectFactory::getInstanceByRefId($a_ref_id, false);
        if (!$obj instanceof ilObject) {
            return $this->raiseError(
                'Error ' . self::SOAP_LP_ERROR_INVALID_REF_ID . ': Invalid reference id ' . $a_ref_id . ' given',
                self::SOAP_LP_ERROR_INVALID_REF_ID
            );
        }

        // check lp available
        include_once './Services/Tracking/classes/class.ilLPObjSettings.php';
        $mode = ilLPObjSettings::_lookupDBMode($obj->getId());
        if ($mode === ilLPObjSettings::LP_MODE_UNDEFINED) {
            return $this->raiseError(
                'Error ' . self::SOAP_LP_ERROR_LP_NOT_AVAILABLE . ': Learning progress not available for objects of type ' .
                $obj->getType(),
                self::SOAP_LP_ERROR_LP_NOT_AVAILABLE
            );
        }

        // check rbac
        /**
         * @var ilAccess
         */
        if (!$ilAccess->checkRbacOrPositionPermissionAccess(
            'read_learning_progress',
            'read_learning_progress',
            $a_ref_id
        )) {
            return $this->raiseError(
                'Error ' . self::SOAP_LP_ERROR_NO_PERMISSION . ': No Permission to access learning progress in this object',
                self::SOAP_LP_ERROR_NO_PERMISSION
            );
        }

        include_once './Services/Xml/classes/class.ilXmlWriter.php';
        $writer = new ilXmlWriter();
        $writer->xmlStartTag(
            'LearningProgressInfo',
            array(
                'ref_id' => $obj->getRefId(),
                'type' => $obj->getType()
            )
        );

        $writer->xmlStartTag('LearningProgressSummary');

        include_once './Services/Tracking/classes/class.ilLPStatusWrapper.php';
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_COMPLETED,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getCompleted($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );
            $completed = count($completed);

            $writer->xmlElement(
                'Status',
                array(
                    'type' => self::PROGRESS_FILTER_COMPLETED,
                    'num' => $completed
                )
            );
        }
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_IN_PROGRESS,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getInProgress($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );
            $completed = count($completed);

            $writer->xmlElement(
                'Status',
                array(
                    'type' => self::PROGRESS_FILTER_IN_PROGRESS,
                    'num' => $completed
                )
            );
        }
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_FAILED,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getFailed($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );
            $completed = count($completed);

            $writer->xmlElement(
                'Status',
                array(
                    'type' => self::PROGRESS_FILTER_FAILED,
                    'num' => $completed
                )
            );
        }
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_NOT_ATTEMPTED,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getNotAttempted($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );
            $completed = count($completed);

            $writer->xmlElement(
                'Status',
                array(
                    'type' => self::PROGRESS_FILTER_NOT_ATTEMPTED,
                    'num' => $completed
                )
            );
        }
        $writer->xmlEndTag('LearningProgressSummary');
        $writer->xmlStartTag('UserProgress');
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_COMPLETED,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getCompleted($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );

            $this->addUserProgress($writer, $completed, self::PROGRESS_FILTER_COMPLETED);
        }
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_IN_PROGRESS,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getInProgress($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );
            $this->addUserProgress($writer, $completed, self::PROGRESS_FILTER_IN_PROGRESS);
        }
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_FAILED,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getFailed($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );
            $this->addUserProgress($writer, $completed, self::PROGRESS_FILTER_FAILED);
        }
        if (in_array(self::PROGRESS_FILTER_ALL, $a_progress_filter) || in_array(
            self::PROGRESS_FILTER_NOT_ATTEMPTED,
            $a_progress_filter
        )) {
            $completed = ilLPStatusWrapper::_getNotAttempted($obj->getId());
            $completed = $GLOBALS['DIC']->access()->filterUserIdsByRbacOrPositionOfCurrentUser(
                'read_learning_progress',
                ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
                $a_ref_id,
                $completed
            );

            $this->addUserProgress($writer, $completed, self::PROGRESS_FILTER_NOT_ATTEMPTED);
        }
        $writer->xmlEndTag('UserProgress');
        $writer->xmlEndTag('LearningProgressInfo');

        return $writer->xmlDumpMem();
    }

    protected function addUserProgress(ilXmlWriter $writer, array $users, int $a_type): void
    {
        foreach ($users as $user_id) {
            $writer->xmlStartTag(
                'User',
                array(
                    'id' => $user_id,
                    'status' => $a_type
                )
            );

            $info = ilObjUser::_lookupName($user_id);
            $writer->xmlElement('Login', array(), (string) $info['login']);
            $writer->xmlElement('Firstname', array(), (string) $info['firstname']);
            $writer->xmlElement('Lastname', array(), (string) $info['lastname']);
            $writer->xmlEndTag('User');
        }
    }

    /**
     * Apply progress filter
     * @param int   $obj_id
     * @param array $usr_ids
     * @param array $filter
     * @return array $filtered_users
     */
    protected function applyProgressFilter(int $obj_id, array $usr_ids, array $filter): array
    {
        include_once './Services/Tracking/classes/class.ilLPStatusWrapper.php';

        $all_users = array();
        if (in_array(self::USER_FILTER_ALL, $usr_ids)) {
            $all_users = array_unique(
                array_merge(
                    ilLPStatusWrapper::_getInProgress($obj_id),
                    ilLPStatusWrapper::_getCompleted($obj_id),
                    ilLPStatusWrapper::_getFailed($obj_id)
                )
            );
        } else {
            $all_users = $usr_ids;
        }

        if (!$filter || in_array(self::PROGRESS_FILTER_ALL, $filter)) {
            $GLOBALS['DIC']['log']->write(__METHOD__ . ': Deleting all progress data');
            return $all_users;
        }

        $filter_users = array();
        if (in_array(self::PROGRESS_FILTER_IN_PROGRESS, $filter)) {
            $GLOBALS['DIC']['log']->write(__METHOD__ . ': Filtering  in progress.');
            $filter_users = array_merge($filter, ilLPStatusWrapper::_getInProgress($obj_id));
        }
        if (in_array(self::PROGRESS_FILTER_COMPLETED, $filter)) {
            $GLOBALS['DIC']['log']->write(__METHOD__ . ': Filtering  completed.');
            $filter_users = array_merge($filter, ilLPStatusWrapper::_getCompleted($obj_id));
        }
        if (in_array(self::PROGRESS_FILTER_FAILED, $filter)) {
            $GLOBALS['DIC']['log']->write(__METHOD__ . ': Filtering  failed.');
            $filter_users = array_merge($filter, ilLPStatusWrapper::_getFailed($obj_id));
        }

        // Build intersection
        return array_intersect($all_users, $filter_users);
    }

    /**
     * Delete SCORM Tracking
     */
    protected function deleteScormTracking(int $a_obj_id, array $a_usr_ids): bool
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = 'DELETE FROM scorm_tracking ' .
            'WHERE ' . $ilDB->in('user_id', $a_usr_ids, false, 'integer') . ' ' .
            'AND obj_id = ' . $ilDB->quote($a_obj_id, 'integer') . ' ';
        $res = $ilDB->manipulate($query);
        return true;
    }

    /**
     * Delete scorm 2004 tracking
     */
    protected function deleteScorm2004Tracking(int $a_obj_id, array $a_usr_ids): void
    {
        global $DIC;

        $ilDB = $DIC['ilDB'];

        $query = 'SELECT cp_node_id FROM cp_node ' .
            'WHERE nodename = ' . $ilDB->quote('item', 'text') . ' ' .
            'AND cp_node.slm_id = ' . $ilDB->quote($a_obj_id, 'integer');
        $res = $ilDB->query($query);

        $scos = array();
        while ($row = $res->fetchRow(ilDBConstants::FETCHMODE_OBJECT)) {
            $scos[] = $row->cp_node_id;
        }

        $query = 'DELETE FROM cmi_node ' .
            'WHERE ' . $ilDB->in('user_id', $a_usr_ids, false, 'integer') . ' ' .
            'AND ' . $ilDB->in('cp_node_id', $scos, false, 'integer');
        $ilDB->manipulate($query);
    }

    /**
     * @param string[] $type_filter
     * @return soap_fault|SoapFault|string|null
     */
    public function getLearningProgressChanges(string $sid, string $timestamp, bool $include_ref_ids, array $type_filter)
    {
        $this->initAuth($sid);
        $this->initIlias();

        if (!$this->checkSession($sid)) {
            return $this->raiseError($this->getMessage(), $this->getMessageCode());
        }
        global $DIC;

        $rbacsystem = $DIC['rbacsystem'];
        $tree = $DIC['tree'];
        $ilLog = $DIC['ilLog'];

        // check administrator
        $types = "";
        if (is_array($type_filter)) {
            $types = implode(",", $type_filter);
        }

        // output lp changes as xml
        try {
            include_once './Services/Tracking/classes/class.ilLPXmlWriter.php';
            $writer = new ilLPXmlWriter(true);
            $writer->setTimestamp($timestamp);
            $writer->setIncludeRefIds($include_ref_ids);
            $writer->setTypeFilter($type_filter);
            $writer->write();

            return $writer->xmlDumpMem(true);
        } catch (UnexpectedValueException $e) {
            return $this->raiseError($e->getMessage(), 'Client');
        }
    }
}

<?php
/*
  +----------------------------------------------------------------------------+
  | ILIAS open source                                                          |
  +----------------------------------------------------------------------------+
  | Copyright (c) 1998-2001 ILIAS open source, University of Cologne           |
  |                                                                            |
  | This program is free software; you can redistribute it and/or              |
  | modify it under the terms of the GNU General Public License                |
  | as published by the Free Software Foundation; either version 2             |
  | of the License, or (at your option) any later version.                     |
  |                                                                            |
  | This program is distributed in the hope that it will be useful,            |
  | but WITHOUT ANY WARRANTY; without even the implied warranty of             |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the              |
  | GNU General Public License for more details.                               |
  |                                                                            |
  | You should have received a copy of the GNU General Public License          |
  | along with this program; if not, write to the Free Software                |
  | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA. |
  +----------------------------------------------------------------------------+
*/

/**
 * Class ilCertificateMigration
 * @author Ralph Dittrich <dittrich@qualitus.de>
 */
class ilCertificateMigration
{
    /** @var int */
    protected $user_id;

    /** @var \ilCertificateMigrationInformationObject */
    protected $information_object;

    /** @var \ilDBInterface */
    protected $db;

    /**
     * ilCertificateMigration constructor.
     * @param int $user_id
     * @param \ilDBInterface $db
     */
    public function __construct(int $user_id, \ilDBInterface $db = null)
    {
        global $DIC;

        $this->user_id = $user_id;
        if (null === $db) {
            $this->db = $DIC->database();
        } else {
            $this->db = $db;
        }
        $this->information_object = new \ilCertificateMigrationInformationObject(
            $this->getTaskInformations()
        );
    }

    /**
     * @return array
     */
    public function getTaskInformations()
    {
        $result = $this->db->queryF(
            'SELECT * FROM bgtask_cert_migration WHERE usr_id = %s',
            ['integer'],
            [$this->user_id]
        );
        if ($result->numRows() == 1)
        {
            $data = $this->db->fetchAssoc($result);
            return $data;
        }
        return [];
    }

    /**
     * @return \ilCertificateMigrationInformationObject
     */
    public function getTaskInformationObject()
    {
        return $this->information_object;
    }

    /**
     * @return float|int
     */
    public function getProgressedItemsAsPercent()
    {
        return (100 / $this->information_object->getFoundItems() * $this->information_object->getProgressedItems());
    }

    /**
     * @return bool
     */
    public function isTaskStarted()
    {
        return $this->information_object->getState() === \ilCertificateMigrationJobDefinitions::CERT_MIGRATION_STATE_INIT;
    }

    /**
     * @return bool
     */
    public function isTaskRunning()
    {
        return (
            $this->information_object->getLock() &&
            $this->information_object->getState() === \ilCertificateMigrationJobDefinitions::CERT_MIGRATION_STATE_RUNNING
        );
    }

    /**
     * @return bool
     */
    public function isTaskFailed()
    {
        return (
            $this->information_object->getState() === \ilCertificateMigrationJobDefinitions::CERT_MIGRATION_STATE_FAILED ||
            $this->information_object->getState() === \ilCertificateMigrationJobDefinitions::CERT_MIGRATION_STATE_STOPPED ||
            (
                $this->information_object->getFinishedTime() === 0 &&
                $this->information_object->getStartingTime() !== 0 &&
                strtotime('-1 hours') > $this->information_object->getStartingTime()
            )
        );
    }

    /**
     * @return bool
     */
    public function isTaskFinished()
    {
        return $this->information_object->getState() === \ilCertificateMigrationJobDefinitions::CERT_MIGRATION_STATE_FINISHED;
    }

}
<?php

/**
 * Elements.at
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) elements.at New Media Solutions GmbH (https://www.elements.at)
 *  @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Elements\Bundle\ProcessManagerBundle\Model\CallbackSetting;

use Elements\Bundle\ProcessManagerBundle\ElementsProcessManagerBundle;
use Elements\Bundle\ProcessManagerBundle\Model\CallbackSetting;
use Elements\Bundle\ProcessManagerBundle\Model\Dao\AbstractDao;

/**
 * Class Dao
 *
 */
class Dao extends AbstractDao
{
    /**
     * @var CallbackSetting
     */
    protected $model;

    public function getTableName()
    {
        return ElementsProcessManagerBundle::TABLE_NAME_CALLBACK_SETTING;
    }

    /**
     * @return $this->model
     */
    public function save()
    {
        $data = $this->getValidStorageValues();
        if (!$data['modificationDate']) {
            $data['modificationDate'] = time();
        }
        if (!$data['creationDate']) {
            $data['creationDate'] = time();
        }
        if (!$data['id']) {
            unset($data['id']);
            $this->db->insert($this->getTableName(), $data);
            $this->model->setId($this->db->lastInsertId($this->getTableName()));
        } else {
            $this->db->update($this->getTableName(), $data, ['id' => $this->model->getId()]);
        }

        return $this->getById($this->model->getId());
    }

    public function delete()
    {
        $id = $this->model->getId();

        if ($id) {
            $this->db
                ->prepare('DELETE FROM ' . $this->getTableName() . ' WHERE `id` = ?')
                ->execute([$id]);

            $this->model = null;
        }
    }
}

<?php
/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class AdminWeArePlanetOrderController extends ModuleAdminController
{
    public function postProcess()
    {
        parent::postProcess();
        exit();
    }

    public function initProcess()
    {
        parent::initProcess();
        $access = Profile::getProfileAccess(
            $this->context->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        if ($access['edit'] === '1' && ($action = Tools::getValue('action'))) {
            $this->action = $action;
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l(
                        'You do not have permission to edit the order.',
                        'adminweareplanetordercontroller'
                    )
                )
            );
            die();
        }
    }

    public function ajaxProcessUpdateOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                WeArePlanetServiceTransactioncompletion::instance()->updateForOrder($order);
                WeArePlanetServiceTransactioncompletion::instance()->updateForOrder($order);
                echo json_encode(array(
                    'success' => 'true'
                ));
                die();
            } catch (Exception $e) {
                echo json_encode(array(
                    'success' => 'false',
                    'message' => $e->getMessage()
                ));
                die();
            }
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminweareplanetordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessVoidOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                WeArePlanetServiceTransactionvoid::instance()->executeVoid($order);
                echo json_encode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the void is processed.',
                            'adminweareplanetordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'success' => 'false',
                        'message' => WeArePlanetHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminweareplanetordercontroller')
                )
            );
            die();
        }
    }

    public function ajaxProcessCompleteOrder()
    {
        if (Tools::isSubmit('id_order')) {
            try {
                $order = new Order(Tools::getValue('id_order'));
                WeArePlanetServiceTransactioncompletion::instance()->executeCompletion($order);
                echo json_encode(
                    array(
                        'success' => 'true',
                        'message' => $this->module->l(
                            'The order is updated automatically once the completion is processed.',
                            'adminweareplanetordercontroller'
                        )
                    )
                );
                die();
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'success' => 'false',
                        'message' => WeArePlanetHelper::cleanExceptionMessage($e->getMessage())
                    )
                );
                die();
            }
        } else {
            echo json_encode(
                array(
                    'success' => 'false',
                    'message' => $this->module->l('Incomplete Request.', 'adminweareplanetordercontroller')
                )
            );
            die();
        }
    }
}

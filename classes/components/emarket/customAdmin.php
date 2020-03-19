<?php

    /** Класс пользовательских методов административной панели */
    class EmarketCustomAdmin {

        /** @var emarket $module */
        public $module;

        /**
         * Перехватывает выполнение дефолтной функции с тем же названием и частично изменяет (дополняет) ее логику.
         *
         * Стандартная функция находится в файле classes/components/emarket/notification.php,
         * строка 18, версия UMI.CMS 21.
         *
         * Описание функции из classes/components/emarket/notification.php:
         * Запускает отправку уведомления об изменении статуса заказа, доставки или оплаты.
         * Устанавливает флаг необходимости экспорта в 1С и дату изменения статуса заказа.
         * @param order $order заказ
         * @param string $changedProperty строковой идентификатор поля заказа, значение которого изменилось
         * @throws selectorException
         */
        public function notifyOrderStatusChange(order $order, $changedProperty) {
            /**
             * Вариант, когда в справочнике создается поле и админ может выбрать, когда включать/выключать синхронизацию
             */
            $status = umiObjectsCollection::getInstance()->getObject($order->getOrderStatus());
            /**
             * 1c_enabled - название поля в справочнике.
             * Изменить эту строку, если создано поле с другим идентификатором.
             */
            $enabledStatus = $status->getValue('1c_enabled');
            $order->need_export = ($enabledStatus == true) ? true : false;

            /**
             * Вариант, когда нужно жестко прописать по определенным статусам
             * case order::getStatusByCode($param)
             * В качестве $param прописать статус заказа для кейса
             */
//            switch ($order->getOrderStatus()) {
//                case order::getStatusByCode('waiting'):
//                    $order->need_export = true;
//                    break;
//                default:
//                    $order->need_export = false;
//                    break;
//            }

            /**
             * Дальше код не менялся
             * Нужен, т.к. код из исходной функции (файл notification.php) больше не выполняется
             */
            if ($changedProperty == 'status_id') {
                $order->status_change_date = new umiDate();
            }

            if (order::getCodeByStatus($order->getPaymentStatus()) == 'accepted' && !$order->delivery_allow_date) {
                $sel = new selector('objects');
                $sel->types('hierarchy-type')->name('emarket', 'delivery');
                $sel->option('no-length')->value(true);
                if ($sel->first()) {
                    $order->delivery_allow_date = new umiDate();
                }
            }

            $statusId = $order->getValue($changedProperty);
            $codeName = order::getCodeByStatus($statusId);

            if ($changedProperty == 'status_id' && (!$statusId || $codeName == 'payment')) {
                return;
            }

            $module = $this->module;
            $module->sendCustomerNotification($order, $changedProperty, $codeName);

            if ($changedProperty == 'status_id' && $codeName == 'waiting') {
                $module->sendManagerNotification($order);
                $module->sendManagerPushNotification($order);
            }

            $order->commit();
        }
    }

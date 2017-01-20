<?php
namespace Phasty\ServiceClient\Exception {

    /**
     * Class ServiceError
     * HTTP Status: 400
     * Исключение выбрасываемое в случае если на сервисе произошла обработанная логическая ошибка.
     * Например: не найден пользователь, нет прав, невалидные данные в запросе.
     *
     * @package Phasty\ServiceClient\Exception
     */
    class ServiceError extends Phasty\ServiceClient\Error {

    }
}
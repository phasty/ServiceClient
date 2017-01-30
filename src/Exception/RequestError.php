<?php
namespace Phasty\ServiceClient\Exception {

    use Phasty\ServiceClient\Error;

    /**
     * Class RequestError
     * HTTP Status: 400
     * Исключение выбрасываемое в случае если на сервисе произошла обработанная логическая ошибка.
     * Например: не найден пользователь, нет прав, невалидные данные в запросе.
     *
     * Исключение должно быть сгенерировано с кодом!
     *
     * @package Phasty\ServiceClient\Exception
     */
    class RequestError extends Error {

    }
}
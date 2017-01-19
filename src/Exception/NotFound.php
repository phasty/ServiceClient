<?php
namespace Phasty\ServiceClient\Exception {

    /**
     * Class NotFound
     * HTTP Status: 404
     * Исключение выбрасываемое в случае если клиент обращается на ресурс, который который существует,
     * на запрошенная в параметрах информация не найдена на сервисе.
     *
     * @package Phasty\ServiceClient\Exception
     */
    class NotFound extends \Exception {

    }
}
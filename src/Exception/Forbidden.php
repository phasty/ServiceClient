<?php
namespace Phasty\ServiceClient\Exception {

    /**
     * Class Forbidden
     * HTTP Status: 403
     * Исключение выбрасываемое в случае если клиент обращается на ресурс, который требует прав доступа,
     * которых нет у клиента.
     * Т.е. ресурс существует, клиент прошел аутентификацию, но при данных параметрах аутентификации доступ невозможен.
     *
     * @package Phasty\ServiceClient\Exception
     */
    class Forbidden extends \Exception {

    }
}
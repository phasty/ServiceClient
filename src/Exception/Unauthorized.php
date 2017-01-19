<?php
namespace Phasty\ServiceClient\Exception {

    /**
     * Class Unauthorized
     * HTTP Status: 401
     * Исключение выбрасываемое в случае если клиент обращается на ресурс, который требует авторизации.
     * Т.е. ресурс существует, но клиент не авторизован (нет сессии), либо ресурс требует передачи
     * ключа доступа (логин/пароль/token и т.п.), но запрос не содержит такого ключа.
     *
     * @package Phasty\ServiceClient\Exception
     */
    class Unauthorized extends \Exception {

    }
}
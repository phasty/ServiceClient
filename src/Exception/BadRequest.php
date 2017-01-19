<?php
namespace Phasty\ServiceClient\Exception {

    /**
     * Class BadRequest
     * HTTP Status: 400
     * Исключение выбрасываемое в случае если клиент присылает неправильный запрос.
     * Т.е. ресурс существует, но клиент шлет запрос, в котором отсутсвуют ключевые параметры.
     *
     * @package Phasty\ServiceClient\Exception
     */
    class BadRequest extends \Exception {

    }
}
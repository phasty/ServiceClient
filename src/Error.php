<?php
namespace Phasty\ServiceClient {

    /**
     * Class Error
     * HTTP Status: 500
     * Исключение выбрасываемое в случае возникновения технической ошибки на стороне сервера.
     * Общий класс, для того чтобы в коде было легко отделить все ошибки сервиса от прочих исключений.
     *
     * @package Phasty\ServiceClient
     */
    abstract class Error extends \Exception {

        /** @var int  INTERNAL_SERVER_ERROR  Ошибка, возникающая в случае серверных сбоев (сервер не может обработать запрос из-за системного сбоя) */
        const INTERNAL_SERVER_ERROR = 1;

        /** @var int  API_NOT_IMPLEMENTED    Ошибка, возникающая в случае запроса на неизвестный ресурс API */
        const API_NOT_IMPLEMENTED   = 2;

        /** @var int  BAD_REQUEST            Ошибка, возникающая в случае некорректного запроса (не хватает ключевых параметров и т.п.) */
        const BAD_REQUEST           = 3;

    }
}
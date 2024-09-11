<?php

namespace Amanank\HalClient\Exceptions;

use Exception;
use GuzzleHttp\Exception\ClientException;

class ConstraintViolationException extends Exception {

    protected $model;
    protected $response;
    protected $id;

    public function __construct($message = "Constraint violation", $code = 409, ClientException $previous = null) {
        parent::__construct($message, $code, $previous);
        if ($previous && $previous->hasResponse() && $previous->getResponse()->getBody()) {
            $this->response = json_decode($previous->getResponse()->getBody()->getContents(), true);
        }
    }

    public function setModel($model, $id) {
        $this->model = $model;
        $this->id = $id;

        $simpleName = class_basename($model);

        $this->message = "Constraint violation for model [{$simpleName}] with id [{$id}].";

        return $this;
    }

    public function getModel() {
        return $this->model;
    }

    public function getId() {
        return $this->id;
    }

    public function getResponse() {
        return $this->response;
    }
}

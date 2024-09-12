<?php

namespace Amanank\HalClient\Exceptions;

use Exception;
use GuzzleHttp\Exception\ClientException;

class ConstraintViolationException extends Exception {

    protected $model;
    protected $errors;
    protected $id;

    public function __construct($message = "Constraint violation", $code = 409, ClientException $previous = null) {
        parent::__construct($message, $code, $previous);
        if ($previous && $previous->hasResponse()) {
            $this->errors = json_decode($previous->getResponse()->getBody()->getContents(), true);
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

    public function getErrors() {
        return $this->errors;
    }
}

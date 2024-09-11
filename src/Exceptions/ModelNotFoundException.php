<?php

namespace Amanank\HalClient\Exceptions;

use Exception;

class ModelNotFoundException extends Exception {

    protected $model;

    protected $id;

    public function __construct($message = 'Model not found', $code = 404, $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function setModel($model, $id) {
        $this->model = $model;
        $this->id = $id;

        $name = class_basename($model);

        $this->message = "Model [$name] with id [$id] not found.";

        return $this;
    }

    public function getModel() {
        return $this->model;
    }

    public function getId() {
        return $this->id;
    }
}

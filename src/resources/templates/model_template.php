<?php

namespace App\Models;

use Amanank\HalClient\HalModel;

class {{className}} extends HalModel
{
    protected $_endpoint = '{{url}}';

    {{attributes}}

    {{relations}}

    {{staticMethods}}

}
<?php

class Product extends Base {
    protected $table = "producto";

    public function __construct($conn) {
        parent::__construct($conn);
    }
}

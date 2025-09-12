<?php

class Stock extends Base {
    protected $table = "producto_talla_stock";

    public function __construct($conn) {
        parent::__construct($conn);
    }
}

<?php

declare(strict_types=1);

namespace App\Common\Contracts;

/**
 * Marca una acción de lectura CQRS.
 *
 * La clase debe exponer un método público `handle({Action}QueryDTO)`
 * con la lógica de lectura. La convención se valida en
 * tests/Unit/CqrsContractTest.php.
 */
interface QueryInterface {}

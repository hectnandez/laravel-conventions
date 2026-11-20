<?php

declare(strict_types=1);

namespace App\Common\Contracts;

/**
 * Marca una acción de escritura CQRS.
 *
 * La clase debe exponer un método público `execute({Action}CommandDTO)`
 * con la lógica de negocio. La convención se valida en
 * tests/Unit/CqrsContractTest.php.
 */
interface CommandInterface {}

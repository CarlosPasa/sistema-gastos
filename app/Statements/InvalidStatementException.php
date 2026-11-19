<?php

namespace App\Statements;

use RuntimeException;

/** El archivo completo no se puede leer (encabezados, tamaño, formato). */
class InvalidStatementException extends RuntimeException {}

<?php

namespace App\Exception;

/** Violation d'une règle métier : le message est destiné à l'utilisateur (en français). */
class BusinessRuleViolation extends \RuntimeException
{
}

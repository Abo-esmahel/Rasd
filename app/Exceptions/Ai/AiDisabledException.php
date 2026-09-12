<?php

namespace App\Exceptions\Ai;

/**
 * التوليد الآلي معطّل — قرار إداري، ليس عطلاً.
 * تُفحص بالـinstanceof (مستقلة عن اللغة) → 403 + تلميح يدوي.
 */
class AiDisabledException extends \InvalidArgumentException
{
}

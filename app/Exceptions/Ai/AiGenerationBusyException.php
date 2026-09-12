<?php

namespace App\Exceptions\Ai;

/**
 * يوجد توليد جارٍ — تُفحص بالـinstanceof (مستقلة عن اللغة) → 409 + مدة انتظار.
 */
class AiGenerationBusyException extends \InvalidArgumentException
{
}

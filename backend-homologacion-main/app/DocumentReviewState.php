<?php

namespace App;

enum DocumentReviewState: string
{
    case Aprobado = 'aprobado';
    case Observado = 'observado';
}

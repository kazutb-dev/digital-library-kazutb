<?php

namespace App\Services\Reports;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** A safe client-visible failure for live reports that require narrower filters. */
final class ReportLimitExceeded extends UnprocessableEntityHttpException {}

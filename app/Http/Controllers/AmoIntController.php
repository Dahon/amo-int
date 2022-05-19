<?php

namespace App\Http\Controllers;

use App\Http\Controllers\components\AmoCrmService;
use App\Http\Controllers\components\AmoTypeConstants;
use Illuminate\Http\Request;


class AmoIntController extends Controller
{
    public function lifeAuto(Request $request) {
        $body = $request->all();
        $typeLead = isset($body['leads']['add']) ? 'add' : (isset($body['leads']['update']) ? 'update' : null);
        if (!$typeLead) return false;
        $pipeId = $body['leads'][$typeLead][0]['pipeline_id'];
        if ($pipeId != AmoTypeConstants::LIFE_PIPELINE_ID) return false;
        $responseBody = $body['leads'][$typeLead][0]['custom_fields'] ?? null;
        if (!$responseBody) return false;
        $id = $body['leads'][$typeLead][0]['id'];
        return 200;
//        return AmoCrmService::test($id, '0003', $responseBody, 1, AmoTypeConstants::LIFE_PIPELINE_ID, AmoTypeConstants::LIFE_PENDING);
    }

    public function autoStart(Request $request) {
        $body = $request->all();
        $typeLead = isset($body['leads']['add']) ? 'add' : (isset($body['leads']['update']) ? 'update' : null);
        if (!$typeLead) return false;
        $pipeId = $body['leads'][$typeLead][0]['pipeline_id'];
        if ($pipeId != AmoTypeConstants::AUTOSTART_PIPELINE_ID) return false;
        $responseBody = $body['leads'][$typeLead][0]['custom_fields'] ?? null;
        if (!$responseBody) return false;
        $id = $body['leads'][$typeLead][0]['id'];
        return AmoCrmService::test($id, '0003', $responseBody, 2, AmoTypeConstants::AUTOSTART_PIPELINE_ID, AmoTypeConstants::AUTOSTART_PENDING);
    }

}

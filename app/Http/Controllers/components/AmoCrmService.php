<?php

  namespace App\Http\Controllers\components;

  use App\Models\AmoTokens;
  use App\Models\Leads;
  use Illuminate\Support\Facades\Log;

  class AmoCrmService
  {
      public static function addLead($leadId, string $note) {
          $integration = AmoTokens::findOrFail(1);
          Log::emergency('test'.json_encode($integration));
          if ($integration && !$integration->access_token) {
              Log::emergency($integration->access_token);
              $integration = self::accessToken($integration);
          }

          Log::emergency($integration);

          if (!$integration) {
              throw new \Exception('AmoCrm integration not found', 4001);
          }

          if (strtotime("now") >= $integration->expired_time) {
              return self::refreshAccessToken($integration);
          }

          self::addNote($integration, $leadId, $note);

          return true;
      }

      public static function changeStatusOfLead($leadId, $requestBody, string $note) {
          $integration = AmoTokens::findOrFail(1);

          if ($integration && !$integration->access_token) {
              $integration = self::accessToken($integration);
          }

          if (strtotime("now") >= $integration->expired_time) {
              return self::refreshAccessToken($integration);
          }

          if (!$integration->access_token) {
              throw new \Exception('AmoCrm integration not found');
          }
          $curl = new CurlTransport();

          $url = $integration->domain . 'api/v4/leads/'.$leadId;

          $headers = [
              'Authorization: Bearer ' . $integration->access_token,
              'Content-Type: application/json'
          ];

          $response = $curl->send($url, $headers, $requestBody, 'PATCH');
          Log::error($response);

          self::addNote($integration, $leadId, $note);
      }

      public static function accessToken($integration) {
          $result = false;

          $url = $integration->domain . 'oauth2/access_token';
          $headers = [
              'Content-Type: application/json'
          ];

          $requestBody = [
              'client_id' => $integration->client_id,
              'client_secret' => $integration->client_secret,
              'grant_type' => 'authorization_code',
              'code' => $integration->code,
              'redirect_uri' => $integration->redirect_url,
          ];

          $curl = new CurlTransport();

          $accessTokenResponse = $curl->send($url, $headers, $requestBody);

          if ($curl->errorNo) {
              Log::emergency($accessTokenResponse);
          }

          if (!$curl->errorNo && $curl->responseCode == 200) {
              $result = self::updateAmocrmToken($accessTokenResponse);
          }
          return $result;
      }

      public static function refreshAccessToken(AmoTokens $integration) {
          $result = false;

          $url = $integration->domain . 'oauth2/access_token';
          $headers = [
              'Content-Type: application/json'
          ];

          $requestBody = [
              'client_id' => $integration->client_id,
              'client_secret' => $integration->client_secret,
              'grant_type' => 'refresh_token',
              'refresh_token' => $integration->refresh_token,
              'redirect_uri' => $integration->redirect_url,
          ];
          $curl = new CurlTransport();

          $refreshTokenResponse = $curl->send($url, $headers, $requestBody);
//        Log::debug('refreshToken response', $refreshTokenResponse);

          if (!$curl->errorNo && $curl->responseCode == 200) {
              $result = self::updateAmocrmToken($refreshTokenResponse);
          }

          return $result;
      }

      public static function addNote($integration, int $entityId, string $note)
      {
          if (!$integration->access_token) {
              throw new \Exception('AmoCrm integration not found');
          }
          $url = $integration->domain . 'api/v4/leads/notes';
          $headers = [
              'Authorization: Bearer ' . $integration->access_token,
              'Content-Type: application/json'
          ];

          $requestBody = [
              [
                  "entity_id" => $entityId,
                  "note_type" => "common",
                  "params" => [
                      "text" => $note
                  ]
              ]
          ];

          $curl = new CurlTransport();
          $noteResponse = $curl->send($url, $headers, $requestBody);
          $noteId = $noteResponse['_embedded']['notes'][0]['id'];
          if (!$noteId) {
              $message = $curl->errorMessage ?: 'Error to create note, leadId = ' . $entityId;
              throw new \Exception($message, 4003);
          }

          return $noteResponse;
      }

      public static function updateAmocrmToken(array $params) {
          $model = AmoTokens::findOrFail(1);
          $model->access_token =  $params['access_token'];
          $model->refresh_token = $params['refresh_token'];
          $model->expires_in = $params['expires_in'];
          $model->expired_time = (time() + $params['expires_in'] - (3600 * 2));
          $model->save();
          return $model;
      }

      public static function getLead($id) {
          return Leads::where('message_id', $id)->get();
      }

  }

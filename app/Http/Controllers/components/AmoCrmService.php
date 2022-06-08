<?php

  namespace App\Http\Controllers\components;

  use App\Models\AmoTokens;
  use App\Models\Leads;
  use Illuminate\Support\Facades\Log;

  class AmoCrmService
  {
      public static function addLead($leadId, string $note, $amoId) {
          $integration = AmoTokens::findOrFail($amoId);
          if ($integration && !$integration->access_token) {
              $integration = self::accessToken($integration, $amoId);
          }

          if (!$integration) {
              throw new \Exception('AmoCrm integration not found', 4001);
          }

          if (strtotime("now") >= $integration->expired_time) {
              $integration = self::refreshAccessToken($integration, $amoId);
          }

          self::addNote($integration, $leadId, $note);

          return true;
      }

      public static function changeStatusOfLead($leadId, $requestBody, string $note, $amoId) {
          $integration = AmoTokens::findOrFail($amoId);
          if ($integration && empty($integration->access_token)) {
              $integration = self::accessToken($integration, $amoId);
          }

          if (strtotime("now") >= $integration->expired_time) {
              $integration = self::refreshAccessToken($integration, $amoId);
          }
          if (empty($integration->access_token)) {
              throw new \Exception('AmoCrm integration not found');
          }
          $curl = new CurlTransport();
          $url = $integration->domain . 'api/v4/leads/'.$leadId;

          $headers = [
              'Authorization: Bearer ' . $integration->access_token,
              'Content-Type: application/json'
          ];

          $response = $curl->send($url, $headers, $requestBody, 'PATCH');
          self::addNote($integration, $leadId, $note);
      }

      public static function accessToken($integration, $amoId) {
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
              $result = self::updateAmocrmToken($accessTokenResponse, $amoId);
          }
          return $result;
      }

      public static function refreshAccessToken(AmoTokens $integration, $amoId) {
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
            Log::debug('refreshToken response', $refreshTokenResponse);

          if (!$curl->errorNo && $curl->responseCode == 200) {
              $result = self::updateAmocrmToken($refreshTokenResponse, $amoId);
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

      public static function updateAmocrmToken(array $params, $amoId) {
          $model = AmoTokens::findOrFail($amoId);
          $model->access_token =  $params['access_token'];
          $model->refresh_token = $params['refresh_token'];
          $model->expires_in = $params['expires_in'];
          $model->expired_time = (time() + $params['expires_in'] - (3600 * 2));
          $model->save();
          return $model;
      }

      public static function getLead($id, $amoId) {
          return Leads::where('company_id', '=', $amoId)->where('message_id', $id)->get();
      }

      public static function test($id, $partnerId, $responseBody, $amoId, $pipeId, $pendingId) {
          $dictionary = [
              'ID partner' => 'partner',
              'ID lead' => "partnerAppId",
              'Номер заявки' => "partnerAppId",
              'Образование' => 'education',
              'Рабочий телефон' => "workPhoneNo",
              'Право проживание' => "regRightResidence",
              'Адрес фактического проживания совпадает с адресом регистрации?' => "regMatchAddrResidence",
              'Метод погашения' => "paymentMtd",
              'Дополнительный доход' => "additionalIncome",
              'Модель ТС' => "model",
              'Являетесь ли залогодателем?' => "pledger",
              'Сумма первоначального взноса' => "downPaymentAmt",
              'Улица' => "regStreet",
              'Телефон по адресу регистрации' => "regPhoneNo",
              'Информация о наличии собственности' => "availabilityOfProperty",
              'Марка ТС' => "brand",
              'Являетесь ли Вы иностранным публичным должностным лицом?' => "foreignPublicPerson",
              'Являетесь ли вы освобожденным от требования требования пенсионных взносов?' => "exemptedPayingMandatoryPensionContributions",
              'Стоимость приобретаемого обьекта' => "costObject",
              'Пробег' => "mileage",
              'Признак резиденства' => "resident",
              'Мобильный телефон' => "mobilePhoneNo",
              'Вид деятельности организации' => "workActivityKind",
              'Должность' => "workPosition",
              'По основному месту работы / доходы от основной предпринимательской деятельности' => "basicIncome",
              'Срок проживания в населенном пункте (мес)' => "durationResidenceSettlement",
              'Стаж работы общий (мес)' => "workExperienceTotal",
              'Сумма кредита' => "loanAmt",
              'Номер документа, уд.лич.' => "docNo",
              'Полное наименование компании/организации' => "workName",
              'Район' => "regDistrict",
              'Дата выдачи документа' => "fromDate",
              'Всего совокупный доход' => "totalIncome",
              'Отчество' => "patronymic",
              'Год выпуска' => "issueYear",
              'Город' => "regCity",
              'Область' => "regRegion",
              'Имя' => "name",
              'Максимальная сумма кредита' => "maxLoanAmt",
              'Количество иждивенцев' => "dependentsCnt",
              'С упрощенным финансовым анализом' => "simpleFinAnalysis",
              'Дата рождения' => "dateBirth",
              'Телефон организации' => "organizationPhoneNo",
              'Пол' => "gender",
              'Всего совокупный расход' => "totalExpenses",
              'ИИН' => "iin",
              'Срок кредита в месяцах' => "loanDuration",
              'БИН' => "workBin",
//            'Наименование обьекта' => "pledgeType",
              'Являетесь ли вы лицом, освобожденным от уплаты обязательных пенсионных взносов?' => "exemptedPayingMandatoryPensionContributions",
              'Ежемесячные расходы (в том числе налоги, взносы, обязательства, алименты, коммунальные платежи и т.д)' => "monthlyExpenses",
              'Фамилия' => "surname",
              'email' => "email",
              'Номер квартиры' => "regApartment",
              'Вид документа, уд. лич.' => "iDocType",
              'Срок проживания по фактическому адресу (мес)' => "durationResidenceActualAddr",
              'Страна производитель марки ТС' => "manufactureCountry",
              'Стаж на последнем месте работы (мес)' => "workExperienceLast",
              'Расположение руля' => "steeringWheel",
              'Номер дома' => "regHouse",
              'Гражданство' => "citizenship",
              'Срок действия документа' => "toDate",
              'Имеется ли работа по совместительству' => "secondJob",
              'Место рождение' => "placeBirth",
              'Орган выдачи документа, уд.лич.' => "issuingAuthority",
              'Точка продаж' => "firmId",
              'Адрес фактического проживания' => "addrActualResidence",
              'Количество детей' => "childrenCnt",
              'Семейное положение' => "family",
              'ФИО#1' => "fullName",
              'Кем приходится заемщику#1' => "relationshipKind",
              'Номер телефона#1' => "phoneNo",
              'ФИО#2' => "fullName2",
              'Кем приходится заемщику#2' => "relationshipKind2",
              'Номер телефона#2' => "phoneNo2",
              'ФИО#3' => "fullName3",
              'Кем приходится заемщику#3' => "relationshipKind3",
              'Номер телефона#3' => "phoneNo3",
          ];
          $leadItem = self::getLead($id, $amoId);
          if (!count($leadItem)) {
              $requestBody = [];
              $contact_person = [];
              $contact_person_2 = [];
              $contact_person_3 = [];
              $requestBody['partner'] = $partnerId;
              $requestBody['partnerAppId'] = $id;
              foreach ($responseBody as $i => $value) {
                  if (isset($dictionary[$value['name']])) {
                      if ($dictionary[$value['name']] == 'fromDate' || $dictionary[$value['name']] == 'toDate' || $dictionary[$value['name']] == 'dateBirth') {
                          $requestBody[$dictionary[$value['name']]] = date('Y-m-d', $value['values'][0]);
                          continue;
                      } elseif ($dictionary[$value['name']] == 'fullName' || $dictionary[$value['name']] == 'phoneNo' ) {
                          $contact_person[$dictionary[$value['name']]] = $value['values'][0]['value'];
                          continue;
                      } elseif ($dictionary[$value['name']] == 'relationshipKind') {
                          if ($value['values'][0]['value'] != '') {
                              $contact_person[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Муж(Жена)' ? '1' : ($value['values'][0]['value'] == 'Отец(Мать)' ? '2' : ($value['values'][0]['value'] == 'Сын(Дочь)' ? '3' : ($value['values'][0]['value'] == 'Брат(Сестра)' ? '4' : ($value['values'][0]['value'] == 'Дедушка(Бабушка)' ? '5' : '6'))));
                          } else {
                              $contact_person[$dictionary[$value['name']]] = '0';
                          }
                          continue;
                      } elseif ($dictionary[$value['name']] == 'fullName2' ) {
                          $contact_person_2['fullName'] = $value['values'][0]['value'];
                          continue;
                      } elseif ($dictionary[$value['name']] == 'phoneNo2' ) {
                          $contact_person_2['phoneNo'] = $value['values'][0]['value'];
                          continue;
                      } elseif ($dictionary[$value['name']] == 'relationshipKind2') {
                          if ($value['values'][0]['value'] != '') {
                              $contact_person_2['relationshipKind'] = $value['values'][0]['value'] == 'Муж(Жена)' ? '1' : ($value['values'][0]['value'] == 'Отец(Мать)' ? '2' : ($value['values'][0]['value'] == 'Сын(Дочь)' ? '3' : ($value['values'][0]['value'] == 'Брат(Сестра)' ? '4' : ($value['values'][0]['value'] == 'Дедушка(Бабушка)' ? '5' : '6'))));
                          } else {
                              $contact_person_2['relationshipKind'] = '0';
                          }
                          continue;
                      } elseif ($dictionary[$value['name']] == 'fullName3') {
                          $contact_person_3['fullName'] = $value['values'][0]['value'];
                          continue;
                      } elseif ($dictionary[$value['name']] == 'phoneNo3' ) {
                          $contact_person_3['phoneNo'] = $value['values'][0]['value'];
                          continue;
                      } elseif ($dictionary[$value['name']] == 'relationshipKind3') {
                          if ($value['values'][0]['value'] != '') {
                              $contact_person_3['relationshipKind'] = $value['values'][0]['value'] == 'Муж(Жена)' ? '1' : ($value['values'][0]['value'] == 'Отец(Мать)' ? '2' : ($value['values'][0]['value'] == 'Сын(Дочь)' ? '3' : ($value['values'][0]['value'] == 'Брат(Сестра)' ? '4' : ($value['values'][0]['value'] == 'Дедушка(Бабушка)' ? '5' : '6'))));
                          } else {
                              $contact_person_3['relationshipKind'] = '0';
                          }
                          continue;
                      }
                      elseif ($dictionary[$value['name']] !== 'regApartment' && $dictionary[$value['name']] !== 'regHouse' && $dictionary[$value['name']] !== 'mobilePhoneNo' && $dictionary[$value['name']] !== 'workPhoneNo' && $dictionary[$value['name']] !== 'workExperienceTotal' && $dictionary[$value['name']] !== 'organizationPhoneNo' && $dictionary[$value['name']] !== 'workExperienceLast' && $dictionary[$value['name']] !== 'regPhoneNo' && $dictionary[$value['name']] !== 'iin' && $dictionary[$value['name']] !== 'workBin' && $dictionary[$value['name']] !== 'docNo' && is_numeric($value['values'][0]['value'])) {
                          $requestBody[$dictionary[$value['name']]] = (int)$value['values'][0]['value'];
                          continue;
                      } elseif ($dictionary[$value['name']] == 'firmId') {
                          $requestBody[$dictionary[$value['name']]] =  '26'; // $value['values'][0]['value'] == 'Алматы' ? '26' : '26';
                          continue;
                      } elseif ($dictionary[$value['name']] == 'paymentMtd') {
                          $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Аннуитет' ? 3 : 4;
                          continue;
                      } elseif ($dictionary[$value['name']] == 'education') {
                          $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Среднее' ? '02' : ($value['values'][0]['value'] == 'Высшее' ? '04' : '05');
                          continue;
                      } elseif ($dictionary[$value['name']] == 'gender') {
                          $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'M' ? '1' : '0';
                          continue;
                      } elseif ($dictionary[$value['name']] == 'secondJob' || $dictionary[$value['name']] == 'exemptedPayingMandatoryPensionContributions' || $dictionary[$value['name']] == 'regMatchAddrResidence' || $dictionary[$value['name']] == 'simpleFinAnalysis' || $dictionary[$value['name']] == 'foreignPublicPerson' || $dictionary[$value['name']] == 'pledger') {
                          $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Да';
                          continue;
                      } elseif ($dictionary[$value['name']] == 'availabilityOfProperty') {
                          $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Да' ? 'true' : 'false';
                      } elseif ($dictionary[$value['name']] == 'family') {
                          $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Семейный(ая)' ? '1' : ($value['values'][0]['value'] == 'Холост (Не замужем)' ? '2' : ($value['values'][0]['value'] == 'Разведен(а)' ? '3' : '4'));
                          continue;
                      }
                      $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'];
                  }
              }
              $requestBody['contactPerson'][] = $contact_person;
              if (!empty($contact_person_2)) {
                  $requestBody['contactPerson'][] = $contact_person_2;
              }
              if (!empty($contact_person_3)) {
                  $requestBody['contactPerson'][] = $contact_person_3;
              }

              $requestBody['pledgeType'] = '11';
              $curl = new CurlTransport();
              $token = '5c33597dcd8c4064a01ab10ebd4bdb12';
              $headers = [
                  'Authorization:'.$token,
                  'Content-Type: application/json'
              ];
              $url = 'https://api.kmf.kz:8443/svc/aster/createApplication';
              Log::emergency('111');
              Log::emergency(json_encode($requestBody));
              $response = $curl->send($url, $headers, $requestBody);
              Log::emergency('kmf-id'.$id.'-response-'.json_encode($response));
              Log::emergency($curl->responseCode);
              if (($curl->responseCode == 500 || $curl->responseCode == 400) && isset($response['Msg'])) {
                  self::addLead($id, $response['Msg'], $amoId);
                  return false;
              }

              if ($curl->responseCode == 500) {
                  self::addLead($id, 'Не валидные данные', $amoId);
                  return false;
              }

              if ($curl->responseCode == 200) {
                  $requestBody['pipeline_id'] = $pipeId;
                  $requestBody['status_id'] = $pendingId;
                  $requestBody['updated_at'] = time();
                  $model = new \App\Models\Leads();
                  $model->application_id = $response['applicationId'];
                  $model->message_id = $id;
                  $model->company_id = $amoId;
                  $model->save();
                  self::changeStatusOfLead($id, $requestBody, 'Заявка отправлена, ждите ответа!', $amoId);
              }
              return 204;
          } else {
//        AmoCrmService::addLead($id, 'Ваша заявка по этой сделке уже отправлена! Либо создайте новый!');
          }

      }

      public static function declined($id, $body, $pipeId, $alterId, $declinedId, $amoId) {
          if (!empty($body)) {
              $desc = explode(',', $body['description']);
              $alter_array = [];
              for ($i = 0; $i < count($desc); $i++) {
                  $val = explode(':', $desc[$i]);
                  $alter_array[$val[0]] = $val[1];
              }
              $requestBody['pipeline_id'] = $pipeId;
              if ($alter_array['duration'] != 0 && $alter_array[' amount'] != 0) {
                  $requestBody['status_id'] = $alterId;
                  $requestBody['updated_at'] = time();
                  $model = Leads::where('company_id', '=', $amoId)->where('application_id', $id)->first();
                  $note = 'Заявка в альтернативах' . PHP_EOL;
                  $note .= 'Продолжительность:' .$alter_array['duration'] . PHP_EOL;
                  $note .= 'Сумма:' .$alter_array[' amount'] . PHP_EOL;
                  $note .= 'Процентная ставка:' .$alter_array[' interest rate'] . PHP_EOL;
                  AmoCrmService::changeStatusOfLead($model->message_id, $requestBody, $note, $amoId);
                  $model->send_status = 2;
                  $model->save();
                  return response()->json(['alter' => $id], 200);
              } else {
                  $requestBody['status_id'] = $declinedId;
                  $requestBody['updated_at'] = time();
                  $model = Leads::where('company_id', '=', $amoId)->where('application_id', $id)->first();
                  AmoCrmService::changeStatusOfLead($model->message_id, $requestBody, 'Заявка отказана', $amoId);
                  $model->send_status = 3;
                  $model->save();
                  return response()->json(['declined' => $id], 200);
              }
          }
      }

  }

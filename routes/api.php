<?php

    use App\Http\Controllers\components\AmoCrmService;
    use App\Http\Controllers\components\AmoTypeConstants;
    use App\Http\Controllers\components\CurlTransport;
    use App\Models\AmoTokens;
    use App\Models\Leads;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Route;
    use App\Http\Controllers\AmoIntController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('autostart/process/declined/{id}', function (Request $request) {
    $body = $request->all();
    Log::info('declined body');
    Log::info(json_encode($body));
    $id = $request->id;
    return AmoCrmService::declined($id, $body, AmoTypeConstants::AUTOSTART_PIPELINE_ID, AmoTypeConstants::AUTOSTART_ALTER, AmoTypeConstants::AUTOSTART_DECLINED, 2);
});

Route::post('token/{id}', function (Request $request) {
    $requestBody = [];
    $note = '';
    AmoCrmService::changeStatusOfLead(1, $requestBody, $note, 2);
});

//Route::post('process/declined/{id}', function (Request $request) {
//    $body = $request->all();
//    Log::info('declined body');
//    Log::info(json_encode($body));
//    $id = $request->id;
//    return AmoCrmService::declined($id, $body, AmoTypeConstants::LIFE_PIPELINE_ID, AmoTypeConstants::LIFE_ALTER, AmoTypeConstants::LIFE_DECLINED, 1);
//});

Route::post('process/declined/{id}', 'App\Http\Controllers\AmoIntController@lifeAutoDeclined')->name('amo.lifeAutoDeclined');

Route::post('process/approved/{id}', function (Request $request) {
    $body = $request->all();
    Log::info('approved body:');
    Log::info(json_encode($body));
    $id = $body['message_id'];
    $requestBody['pipeline_id'] = AmoTypeConstants::LIFE_PIPELINE_ID;
    $requestBody['status_id'] = AmoTypeConstants::LIFE_APPROVED_ID;
    $requestBody['updated_at'] = time();
    AmoCrmService::changeStatusOfLead($id, $requestBody, 'Заявка одобрена', 1);
    $model = Leads::where('company_id', '=', 1)->where('message_id', $id)->first();
    $model->send_status = 1;
    $model->save();
    return 200;
});

Route::post('autostart/process/approved/{id}', function (Request $request) {
    $body = $request->all();
    Log::info('approved body:');
    Log::info(json_encode($body));
    $id = $body['message_id'];
    $requestBody['pipeline_id'] = AmoTypeConstants::AUTOSTART_PIPELINE_ID;
    $requestBody['status_id'] = AmoTypeConstants::AUTOSTART_APPROVED_ID;
    $requestBody['updated_at'] = time();
    AmoCrmService::changeStatusOfLead($id, $requestBody, 'Заявка одобрена', 2);
    $model = Leads::where('company_id', '=', 2)->where('message_id', $id)->first();
    $model->send_status = 1;
    $model->save();
    return 200;
});

Route::post('process/add', function (Request $request) {
        $body = $request->all();
        $model = new \App\Models\Leads();
        $model->application_id = $body['application_id'];
        $model->message_id = (int)$body['message_id'];
        $model->company_id = 2;
        $model->save();
        return 200;
    });

Route::post('test', 'AmoIntController@lifeAuto')->name('amo.lifeAuto');
Route::post('autostart', 'AmoIntController@autoStart')->name('amo.autoStart');

//Route::post('test', function (Request $request) {
//    $body = $request->all();
//    $typeLead = isset($body['leads']['add']) ? 'add' : (isset($body['leads']['update']) ? 'update' : null);
//    if (!$typeLead) return false;
//    $pipeId = $body['leads'][$typeLead][0]['pipeline_id'];
//    if ($pipeId != PIPELINE_ID) return false;
//    $responseBody = $body['leads'][$typeLead][0]['custom_fields'] ?? null;
//    if (!$responseBody) return false;
//
//
//    $id = $body['leads'][$typeLead][0]['id'];
//    $dictionary = [
//        'ID partner' => 'partner',
//        'ID lead' => "partnerAppId",
//        'Номер заявки' => "partnerAppId",
//        'Образование' => 'education',
//        'Рабочий телефон' => "workPhoneNo",
//        'Право проживание' => "regRightResidence",
//        'Адрес фактического проживания совпадает с адресом регистрации?' => "regMatchAddrResidence",
//        'Метод погашения' => "paymentMtd",
//        'Дополнительный доход' => "additionalIncome",
//        'Модель ТС' => "model",
//        'Являетесь ли залогодателем?' => "pledger",
//        'Сумма первоначального взноса' => "downPaymentAmt",
//        'Улица' => "regStreet",
//        'Телефон по адресу регистрации' => "regPhoneNo",
//        'Информация о наличии собственности' => "availabilityOfProperty",
//        'Марка ТС' => "brand",
//        'Являетесь ли Вы иностранным публичным должностным лицом?' => "foreignPublicPerson",
//        'Являетесь ли вы освобожденным от требования требования пенсионных взносов?' => "exemptedPayingMandatoryPensionContributions",
//        'Стоимость приобретаемого обьекта' => "costObject",
//        'Пробег' => "mileage",
//        'Признак резиденства' => "resident",
//        'Мобильный телефон' => "mobilePhoneNo",
//        'Вид деятельности организации' => "workActivityKind",
//        'Должность' => "workPosition",
//        'По основному месту работы / доходы от основной предпринимательской деятельности' => "basicIncome",
//        'Срок проживания в населенном пункте (мес)' => "durationResidenceSettlement",
//        'Стаж работы общий (мес)' => "workExperienceTotal",
//        'Сумма кредита' => "loanAmt",
//        'Номер документа, уд.лич.' => "docNo",
//        'Полное наименование компании/организации' => "workName",
//        'Район' => "regDistrict",
//        'Дата выдачи документа' => "fromDate",
//        'Всего совокупный доход' => "totalIncome",
//        'Отчество' => "patronymic",
//        'Год выпуска' => "issueYear",
//        'Город' => "regCity",
//        'Область' => "regRegion",
//        'Имя' => "name",
//        'Максимальная сумма кредита' => "maxLoanAmt",
//        'Количество иждивенцев' => "dependentsCnt",
//        'С упрощенным финансовым анализом' => "simpleFinAnalysis",
//        'Дата рождения' => "dateBirth",
//        'Телефон организации' => "organizationPhoneNo",
//        'Пол' => "gender",
//        'Всего совокупный расход' => "totalExpenses",
//        'ИИН' => "iin",
//        'Срок кредита в месяцах' => "loanDuration",
//        'БИН' => "workBin",
////            'Наименование обьекта' => "pledgeType",
//        'Являетесь ли вы лицом, освобожденным от уплаты обязательных пенсионных взносов?' => "exemptedPayingMandatoryPensionContributions",
//        'Ежемесячные расходы (в том числе налоги, взносы, обязательства, алименты, коммунальные платежи и т.д)' => "monthlyExpenses",
//        'Фамилия' => "surname",
//        'email' => "email",
//        'Номер квартиры' => "regApartment",
//        'Вид документа, уд. лич.' => "iDocType",
//        'Срок проживания по фактическому адресу (мес)' => "durationResidenceActualAddr",
//        'Страна производитель марки ТС' => "manufactureCountry",
//        'Стаж на последнем месте работы (мес)' => "workExperienceLast",
//        'Расположение руля' => "steeringWheel",
//        'Номер дома' => "regHouse",
//        'Гражданство' => "citizenship",
//        'Срок действия документа' => "toDate",
//        'Имеется ли работа по совместительству' => "secondJob",
//        'Место рождение' => "placeBirth",
//        'Орган выдачи документа, уд.лич.' => "issuingAuthority",
//        'Точка продаж' => "firmId",
//        'Адрес фактического проживания' => "addrActualResidence",
//        'Количество детей' => "childrenCnt",
//        'Семейное положение' => "family",
//        'ФИО' => "fullName",
//        'Кем приходится заемщику' => "relationshipKind",
//        'Номер телефона' => "phoneNo",
//    ];
//    $leadItem = AmoCrmService::getLead($id);
//    if (!count($leadItem)) {
//        $requestBody = [];
//        $contact_person = [];
//        $requestBody['partner'] = '0003';
//        $requestBody['partnerAppId'] = $id;
//        foreach ($responseBody as $i => $value) {
//            if (isset($dictionary[$value['name']])) {
//                if ($dictionary[$value['name']] == 'fromDate' || $dictionary[$value['name']] == 'toDate' || $dictionary[$value['name']] == 'dateBirth') {
//                    $requestBody[$dictionary[$value['name']]] = date('Y-m-d', $value['values'][0]);
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'fullName' || $dictionary[$value['name']] == 'phoneNo' ) {
//                    $contact_person[$dictionary[$value['name']]] = $value['values'][0]['value'];
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'relationshipKind') {
//                    if ($value['values'][0]['value'] != '') {
//                        $contact_person[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Муж(Жена)' ? '1' : ($value['values'][0]['value'] == 'Отец(Мать)' ? '2' : ($value['values'][0]['value'] == 'Сын(Дочь)' ? '3' : ($value['values'][0]['value'] == 'Брат(Сестра)' ? '4' : ($value['values'][0]['value'] == 'Дедушка(Бабушка)' ? '5' : '6'))));
//                    } else {
//                        $contact_person[$dictionary[$value['name']]] = '0';
//                    }
//                    continue;
//                }
//                elseif ($dictionary[$value['name']] !== 'regApartment' && $dictionary[$value['name']] !== 'regHouse' && $dictionary[$value['name']] !== 'mobilePhoneNo' && $dictionary[$value['name']] !== 'workPhoneNo' && $dictionary[$value['name']] !== 'workExperienceTotal' && $dictionary[$value['name']] !== 'organizationPhoneNo' && $dictionary[$value['name']] !== 'workExperienceLast' && $dictionary[$value['name']] !== 'regPhoneNo' && $dictionary[$value['name']] !== 'iin' && $dictionary[$value['name']] !== 'workBin' && $dictionary[$value['name']] !== 'docNo' && is_numeric($value['values'][0]['value'])) {
//                    $requestBody[$dictionary[$value['name']]] = (int)$value['values'][0]['value'];
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'firmId') {
//                    $requestBody[$dictionary[$value['name']]] =  '26'; // $value['values'][0]['value'] == 'Алматы' ? '26' : '26';
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'paymentMtd') {
//                    $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Аннуитет' ? 3 : 4;
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'education') {
//                    $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Среднее' ? '02' : ($value['values'][0]['value'] == 'Высшее' ? '04' : '05');
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'gender') {
//                    $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'M' ? '1' : '0';
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'secondJob' || $dictionary[$value['name']] == 'exemptedPayingMandatoryPensionContributions' || $dictionary[$value['name']] == 'regMatchAddrResidence' || $dictionary[$value['name']] == 'simpleFinAnalysis' || $dictionary[$value['name']] == 'foreignPublicPerson' || $dictionary[$value['name']] == 'pledger') {
//                    $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Да';
//                    continue;
//                } elseif ($dictionary[$value['name']] == 'availabilityOfProperty') {
//                    $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Да' ? 'true' : 'false';
//                } elseif ($dictionary[$value['name']] == 'family') {
//                    $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'] == 'Семейный(ая)' ? '1' : ($value['values'][0]['value'] == 'Холост (Не замужем)' ? '2' : ($value['values'][0]['value'] == 'Разведен(а)' ? '3' : '4'));
//                    continue;
//                }
//                $requestBody[$dictionary[$value['name']]] = $value['values'][0]['value'];
//            }
//        }
//        $requestBody['contactPerson'][] = $contact_person;
//        $requestBody['pledgeType'] = '11';
//        $curl = new CurlTransport();
//        $token = '5c33597dcd8c4064a01ab10ebd4bdb12';
//        $headers = [
//            'Authorization:'.$token,
//            'Content-Type: application/json'
//        ];
//        $url = 'https://api.kmf.kz:8443/svc/aster/createApplication';
//        $response = $curl->send($url, $headers, $requestBody);
//        Log::emergency('kmf-id'.$id.'-response-'.json_encode($response));
//        Log::emergency($curl->responseCode);
//        if (($curl->responseCode == 500 || $curl->responseCode == 400) && isset($response['Msg'])) {
//            AmoCrmService::addLead($id, $response['Msg']);
//            return false;
//        }
//
//        if ($curl->responseCode == 500) {
//            AmoCrmService::addLead($id, 'Не валидные данные');
//            return false;
//        }
//
//        if ($curl->responseCode == 200) {
//            $requestBody['pipeline_id'] = PIPELINE_ID;
//            $requestBody['status_id'] = PENDING;
//            $requestBody['updated_at'] = time();
//            $model = new \App\Models\Leads();
//            $model->application_id = $response['applicationId'];
//            $model->message_id = $id;
//            $model->save();
//            AmoCrmService::changeStatusOfLead($id, $requestBody, 'Заявка отправлена, ждите ответа!');
//        }
//        return 204;
//    } else {
////        AmoCrmService::addLead($id, 'Ваша заявка по этой сделке уже отправлена! Либо создайте новый!');
//    }
//
//});

Route::post('webhook', function (Request $request) {
    $bot = Telegram::bot('mybot');
    $result = $bot->getWebhookUpdates();
    $chat_id = $result->getChat()->getId();
    $bot->sendMessage([
        'text' => 'Пока данных нет',
        'chat_id' => $chat_id
    ]);
    Log::error('bot'.json_encode($result));
});

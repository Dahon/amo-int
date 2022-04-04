<?php

    use App\Http\Controllers\components\AmoCrmService;
    use App\Http\Controllers\components\CurlTransport;
    use App\Models\AmoTokens;
    use App\Models\Leads;
    use Illuminate\Http\Request;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Route;

    const PIPELINE_ID = 5287444;
    const PENDING = 47135884;
    const APPROVED_ID = 47135872;
    const ALTER = 47135875;
    const DECLINED = 47135878;
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

Route::post('process/declined/{id}', function (Request $request) {
    $body = $request->all();
    Log::info('declined body');
    Log::info(json_encode($body));
    $id = $request->id;
    $requestBody['pipeline_id'] = PIPELINE_ID;
    if (!empty($body)) {
        $desc = explode(',', $body['description']);
        $alter_array = [];
        for ($i = 0; $i < count($desc); $i++) {
            $val = explode(':', $desc[$i]);
            $alter_array[$val[0]] = $val[1];
        }
        if ($alter_array['duration'] != 0 && $alter_array[' amount'] != 0) {
            $requestBody['status_id'] = ALTER;
            $requestBody['updated_at'] = time();
            $model = Leads::where('application_id', $id)->first();
            $note = 'Заявка в альтернативах' . PHP_EOL;
            $note .= 'Продолжительность:' .$alter_array['duration'] . PHP_EOL;
            $note .= 'Сумма:' .$alter_array[' amount'] . PHP_EOL;
            $note .= 'Процентная ставка:' .$alter_array[' interest rate'] . PHP_EOL;
            AmoCrmService::changeStatusOfLead($model->message_id, $requestBody, $note);
            $model->send_status = 2;
            $model->save();
            return response()->json(['alter' => $id], 200);
        } else {
            $requestBody['status_id'] = DECLINED;
            $requestBody['updated_at'] = time();
            $model = Leads::where('application_id', $id)->first();
            AmoCrmService::changeStatusOfLead($model->message_id, $requestBody, 'Заявка отказана');
            $model->send_status = 3;
            $model->save();
            return response()->json(['declined' => $id], 200);
        }
    }
});

Route::post('process/add', function (Request $request) {
    $body = $request->all();
    $model = new \App\Models\Leads();
    $model->application_id = $body['application_id'];
    $model->message_id = (int)$body['message_id'];
    $model->save();
    return 200;
});

Route::post('process/approved/{id}', function (Request $request) {
    $body = $request->all();
    Log::info('approved body:');
    Log::info(json_encode($body));
    $id = $body['message_id'];
    $requestBody['pipeline_id'] = PIPELINE_ID;
    $requestBody['status_id'] = APPROVED_ID;
    $requestBody['updated_at'] = time();

    AmoCrmService::changeStatusOfLead($id, $requestBody, 'Заявка одобрена');

    $model = Leads::where('message_id', $id)->first();
    $model->send_status = 1;
    $model->save();
    return 200;
});

Route::post('test', function (Request $request) {
    $body = $request->all();
    $typeLead = isset($body['leads']['add']) ? 'add' : (isset($body['leads']['update']) ? 'update' : null);
    if (!$typeLead) return false;
    $pipeId = $body['leads'][$typeLead][0]['pipeline_id'];
    if ($pipeId != PIPELINE_ID) return false;
    $responseBody = $body['leads'][$typeLead][0]['custom_fields'] ?? null;
    if (!$responseBody) return false;
    $id = $body['leads'][$typeLead][0]['id'];
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
        'ФИО' => "fullName",
        'Кем приходится заемщику' => "relationshipKind",
        'Номер телефона' => "phoneNo",
    ];
    $leadItem = AmoCrmService::getLead($id);
    if (!count($leadItem)) {
        $requestBody = [];
        $contact_person = [];
        $requestBody['partner'] = '0003';
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
                    $contact_person[$dictionary[$value['name']]] = '0';
                    continue;
                }
                elseif ($dictionary[$value['name']] !== 'regApartment' && $dictionary[$value['name']] !== 'regHouse' && $dictionary[$value['name']] !== 'mobilePhoneNo' && $dictionary[$value['name']] !== 'workPhoneNo' && $dictionary[$value['name']] !== 'workExperienceTotal' && $dictionary[$value['name']] !== 'organizationPhoneNo' && $dictionary[$value['name']] !== 'workExperienceLast' && $dictionary[$value['name']] !== 'regPhoneNo' && $dictionary[$value['name']] !== 'iin' && $dictionary[$value['name']] !== 'workBin' && $dictionary[$value['name']] !== 'docNo' && is_numeric($value['values'][0]['value'])) {
                    $requestBody[$dictionary[$value['name']]] = (int)$value['values'][0]['value'];
                    continue;
                } elseif ($dictionary[$value['name']] == 'firmId') {
                    $requestBody[$dictionary[$value['name']]] =  'test'; // $value['values'][0]['value'] == 'Алматы' ? '26' : '16';
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
        $requestBody['pledgeType'] = '11';
        $curl = new CurlTransport();
        $token = '5c33597dcd8c4064a01ab10ebd4bdb12';
        $headers = [
            'Authorization:'.$token,
            'Content-Type: application/json'
        ];
        $url = 'https://api.kmf.kz/svc/aster/createApplication';
        $response = $curl->send($url, $headers, $requestBody);
        Log::emergency($response);
        Log::emergency($curl->responseCode);
        if (($curl->responseCode == 500 || $curl->responseCode == 400) && isset($response['Msg'])) {
            AmoCrmService::addLead($id, $response['Msg']);
            return false;
        }

        if ($curl->responseCode == 500) {
            AmoCrmService::addLead($id, 'Не валидные данные');
            return false;
        }

        if ($curl->responseCode == 200) {
            $requestBody['pipeline_id'] = PIPELINE_ID;
            $requestBody['status_id'] = PENDING;
            $requestBody['updated_at'] = time();
            $model = new \App\Models\Leads();
            $model->application_id = $response['applicationId'];
            $model->message_id = $response['asterId'];
            $model->save();
            AmoCrmService::changeStatusOfLead($id, $requestBody, 'Заявка отправлена, ждите ответа!');
        }
        return 204;
    } else {
    }

});

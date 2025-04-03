<?php

require 'auth.php';


$userSearchResult = CRest::get('user.search', [
    'filter' => [
        'ACTIVE' => 1,
    ]
]);

$userArray = [];
foreach ($userSearchResult['result'] as $userInfo) {
    $userArray[$userInfo['ID']] = trim($userInfo['LAST_NAME'] . ' ' . $userInfo['NAME']);
}

$dealListResult = CRest::get('crm.deal.list',[
    'filter' => [
        'STAGE_ID' => 'PREPAYMENT_INVOICE',
        'UF_CRM_1742808547751' => ''
    ],
    'select' => ['ID', 'COMPANY_ID', 'CONTACT_ID', 'ASSIGNED_BY_ID'],
]);

foreach ($dealListResult['result'] as $deal) {

    $contactGetResult = CRest::get('crm.contact.get',[
        'id' => $deal['CONTACT_ID'],
        'select' => ['ID', 'UF_CRM_1742810114881'],
    ]);

    $companyGetResult = CRest::get('crm.company.get',[
        'id' => $deal['COMPANY_ID'],
        'select' => ['ID', 'UF_CRM_1742810114881'],
    ]);

    // Отправка сделки в 1С ...
    $exportData = [
        'dealId' => $deal['ID'],
        'idCompany' => $deal['COMPANY_ID'],
        'uinCompany' => $contactGetResult['result']['UF_CRM_1742810135146'],
        'idContact' => $deal['CONTACT_ID'],
        'uinContact' => $contactGetResult['result']['UF_CRM_1742810114881'],
        'responsible_user' => $userArray[$deal['ASSIGNED_BY_ID']] ?? 'Неизвестный пользователь',
    ];

    echo '<pre>';
    print_r($exportData);
    echo '</pre>';

    // ... Метод отправки в 1С

    // Когда забираем сделку - заполняем поле “Отдали в 1С” значением - да
   /* CRest::get('crm.deal.update',[
        'id' => $deal['ID'],
        'fields' => [
            'UF_CRM_1742808547751' => 94
        ],
    ]);*/

}




/*1С (СОЗДАНИЕ КП):

Раз в 30 секунд 1С делает запрос в Б24 и пытается получить новые сделки в воронке “Общая” (CATEGORY_ID = 0) на этапе “КП формируем” (STAGE_ID = ?). Взять из них поля и создать Коммерческое предложение в 1С.

Когда забираем сделку - заполняем поле “Отдали в 1С” значением - да (UF_CRM_1742808547751).
Когда в след раз проверяем все сделки на этапе “КП формируем” - смотрим на поле “Отдали в 1С” (UF_CRM_1742808547751). Если оно заполнено значением - да, то Коммерческое предложение в 1С не создаем.

Для создание Коммерческого предложения в 1С  надо взять Необходимые поля из сделок в Битрикс24:

ID сделки (DEAL_ID):
ID компании (COMPANY_ID):
УИН компании (UF_CRM_1742810135146)
ID контакта (CONTACT_ID):
УИН контакта (UF_CRM_1742810114881)
Ответственный (ASSIGNED_BY_ID):


ЗАПРОС:

POST https://yourcompany.bitrix24.ru/rest/1234/abcd5678/crm.deal.list*/

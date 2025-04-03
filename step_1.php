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

    // ... Метод отправки в 1С

    // Когда забираем сделку - заполняем поле “Отдали в 1С” значением - да
    CRest::get('crm.deal.update',[
        'id' => $deal['ID'],
        'fields' => [
            'UF_CRM_1742808547751' => 94
        ],
    ]);

}

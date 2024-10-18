<?php

$camera = file_get_contents('camera.json');

$cameras = json_decode($camera, true)['value'][1][0];



// echo '<pre>';
// print_r($cameras);
// echo '</pre>';

$id = [];

foreach ($cameras as $key => $value) {
    $camStatusIndex = array_search('UP', array_column($value['Properties'], 'Value', 'Name'));
    $ManagementUnit = array_search('ManagementUnit', array_column($value['Properties'], 'Name', 'Value'));
    if ($camStatusIndex === false) {
        continue;
    }

    $id[] = [
        'CamId' => $value['Properties'][0]['Value'],
        'CamName' => $value['Properties'][1]['Value'],
        'Disctrict' => ($value['Properties'][4]['Value'] === 'UP' ? 'N/A' : $value['Properties'][4]['Value']),
        'SnapshotUrl' => $value['Properties'][2]['Value'],
        'ManagementUnit' => $ManagementUnit,
    ];

    /*if ($value['Properties'][$camStatusIndex]['Value'] !== 'NOT_IMAGE') {
        // $id[] = $value['Properties'][0]['Value'];
        // $camStatusIndex = isset($value['Properties'][6]) && $value['Properties'][6]['Name'] === 'CamStatus' ? 6 : 4;
        $id[$key][] = [
            'id' => $value['Properties'][0]['Value'],
            'name' => $value['Properties'][1]['Value'],
            'Disctrict' => $value['Properties'][4]['Value'],
            'url' => $value['Properties'][5]['Value'],
            'status' => $value['Properties'][$camStatusIndex]['Value'],
        ];
        // $id[$key][] = [
        //     'id' => $value['Properties'][0]['Value'],
        //     'name' => $value['Properties'][1]['Value'],
        //     'Disctrict' => $value['Properties'][4]['Value'],
        //     'url' => $value['Properties'][5]['Value'],
        // ];
    }*/
}
header('Content-Type: application/json');
echo json_encode($id);

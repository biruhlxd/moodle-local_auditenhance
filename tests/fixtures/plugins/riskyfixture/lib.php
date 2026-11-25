<?php
// phpcs:ignoreFile
// Fixture code with deliberate problems for the plugin risk tests. It is never included.

function local_riskyfixture_bad(int $id, string $name) {
    global $DB;
    $code = $_POST['code'];
    eval($code);
    $out = shell_exec('ls');
    $data = unserialize(optional_param('data', '', PARAM_RAW));
    $safe = unserialize($DB->get_field('config', 'value', ['name' => 'x']));
    $rows = $DB->get_records_sql("SELECT * FROM {user} WHERE id = $id");
    $rows = $DB->get_records_select('user', "username = '" . $name . "'");
    $rows = $DB->get_records_sql('SELECT * FROM {user} WHERE id = :id', ['id' => $id]);
    $rows = $DB->get_records_sql('SELECT * FROM {user} ' . $where, $params);
    print_error('nopermissions');
    $obj->exec('fine');
    return $_GET['x'];
}

<?php

//CONFIGURACOES PADROES DO WORDPRESS
require "../../../wp-config.php";

//CARREGA A BIBLIOTECA PADRAO
require "../../../wp-includes/pluggable.php";

//VERIFICA SE O USUARIO ESTA LOGADO NO ADMIN
if (is_user_logged_in ()) {

    $conexao = mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
    if ($conexao) {
        if (isset($_REQUEST['id'])) {
            mysql_select_db(DB_NAME);
            $idExportar = (int) $_REQUEST['id'];

            $sql = "select post_title from {$table_prefix}posts where ID = {$idExportar}";
            $ret = mysql_query($sql, $conexao);

            if ($ret) {
                $pagina = mysql_fetch_assoc($ret);
                $sql = "select email,data_voto,confirmado from {$table_prefix}wpvotacao where post_id = {$idExportar} order by email";
                $ret = mysql_query($sql, $conexao);

                $csv = utf8_decode("Lista de votos: " . $pagina['post_title']) . "\n";
                $csv .= "Email;Data Voto;Confirmado\n";

                while ($row = mysql_fetch_array($ret)) {
                    $csv .= "{$row['email']};{$row['data_voto']};".($row['confirmado']=='1'?'Sim':utf8_decode('Não'))."\n";
                }
                header("Content-type: application/ms-excel");
                header("Content-Disposition: attachment; filename=\"Lista_Usuarios-{$idExportar}.csv\"");
                echo $csv;
            }
        }
        mysql_close($conexao);
    }
} else {
    header("HTTP/1.0 401 Authorization Required");
    echo "N&Atilde;O AUTORIZADO";
}
?>

<?php
/*
  Plugin Name: VotaÃ§Ã£o de Posts
  Description: VotaÃ§Ã£o para classificaÃ§Ã£o de posts
  Author: Claudney S. Reis <claudsan@gmail.com>
  Version: 1.0
  Author URI: http://cucadigital.com.br/plugins
 */

/**
 * VotaÃ§Ã£o de Posts, com confirmaÃ§Ã£o  por email.
 * @author Claudney S. Reis <claudsan@gmail.com>
 */

require_once(ABSPATH . '/wp-admin/includes/plugin.php');
require_once(ABSPATH . WPINC . '/pluggable.php');

class WpVotacao {

    //VARIAVEIS GLOBAIS
    private static
    $wpdb, $info;
    //DROPAR AS TABELAS NO UNISTALL
    private static $drop = false;

    /**
     * CONSTRUTOR
     * @global object $wpdb
     */
    public function __construct() {
        global $wpdb;

        //MAPEAMENTO DOS OBJETOS
        self::$wpdb = $wpdb;

        //OUTROS MAPEAMENTOS
        self::$info['plugin_fpath'] = dirname(__FILE__);
    }

    /**
     * INICIALIZACAO DO PLUGIN
     */
    public static function inicializar() {
        self::adicionarMenu();
    }

    public function instalar() {

        if (is_null(self::$wpdb)) {
            throw new Exception("Mapeamento dos objetos nao encontrados");
        }

        $prefix = self::$wpdb->prefix;

        $tabCadastro = "CREATE  TABLE IF NOT EXISTS `{$prefix}wpvotacao` (
                          `id` INT NOT NULL AUTO_INCREMENT ,
                          `post_id` INT NOT NULL ,
                          `email` VARCHAR(255) NOT NULL ,
                          `codigo` VARCHAR(64) NOT NULL ,
                          `confirmado` TINYINT(1)  NOT NULL DEFAULT 0 ,
                          `data_voto` datetime,
                          PRIMARY KEY (`id`) )
                        ENGINE = MyISAM";

        $tabCadastroIdx = "ALTER TABLE `{$prefix}wpvotacao` ADD UNIQUE INDEX `IDX_VOTACAO_UNICA_EMAIL_POST` (`post_id`, `email`);";

        self::$wpdb->query($tabCadastro);
        self::$wpdb->query($tabCadastroIdx);
    }

    /**
     * REMOVE AS TABELAS CRIADAS PELO PLUGIN
     */
    public function desinstalar() {
        if (self::$drop) {
            $sql1 = "DROP TABLE `" . self::$wpdb->prefix . "wpvotacao`";
            self::$wpdb->query($sql1);
        }
    }

    /**
     * ADICIONA A OPCAO NO MENU DO ADMIN
     */
    public static function adicionarMenu() {
        add_options_page('Votação de Posts', 'Votação/Listagem', 10, __FILE__, array("WpVotacao", "listaVotacao"));
    }

    public function formataMensagem($msg) {
        return "<div class=\"updated fade\"><p>{$msg}</p></div>";
    }

    /**
     * GERA OS FORMULARIOS DA VOTACAO
     * @global object $wpdb
     * @global object $wp_query
     */
    public static function geraVotacao() {
        global $wpdb;
        global $wp_query;

        //ID DA PAGINA ATUAL
        $postID = $wp_query->post->ID;

        //INICIA A SESSAO
        @session_start();
        echo '<link type="text/css" rel="stylesheet" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/wp-votacao/css/votacao.css" />' . "\n";

        $linkPost = get_permalink();
        $captchaURL = get_bloginfo('url') . "/wp-content/plugins/wp-votacao/captcha.php";

        //CARREGA O TEMPLATE DE VOTACAO
        $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateVotacao.html");

        $htmlVoto = str_replace("#POSTID#", $postID, $htmlVoto);
        $htmlVoto = str_replace("#PATH_CAPTCHA#", $captchaURL, $htmlVoto);
        $htmlVoto = str_replace("#LINK_VOTACAO#", $linkPost, $htmlVoto);

        //CONTA A QTD DE VOTOS
        $sql = "select count(1) as qtd from `" . $wpdb->prefix . "wpvotacao` where post_id = {$postID} and confirmado = 1";
        $ret = $wpdb->get_results($sql);
        if ($ret) {
            $htmlVoto = str_replace("#QTD_VOTOS#", $ret[0]->qtd, $htmlVoto);
        } else {
            $htmlVoto = str_replace("#QTD_VOTOS#", '0', $htmlVoto);
        }

        if (isset($_REQUEST['confirmvote'])) {
            $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateConfirmacao.html");
            $htmlVoto = str_replace("#LINK_VOTACAO#", $linkPost, $htmlVoto);
            $htmlVoto = str_replace("#EMAIL#", $_REQUEST['email'], $htmlVoto);
            $htmlVoto = str_replace("#IDVOTO#", $_REQUEST['idvoto'], $htmlVoto);

            if (isset($_REQUEST['confirm']) && $_REQUEST['confirm'] == 'now') {
                $_POST['emailVoto'] = str_replace(array("#", "%", "=", ">", "<", "!"), "", $_POST['emailVoto']);
                $sql = "select id,email,codigo,confirmado from `" . $wpdb->prefix . "wpvotacao` where email = '{$_REQUEST['emailVoto']}' limit 1";
                $ret = $wpdb->get_results($sql);

                if ($ret) {
                    //VERIFICA SE O VOTO JA FOI CONFIRMADO
                    if ($ret[0]->confirmado === '0') {
                        //VERIFICA SE O CODIGO E VALIDO COM O HASH
                        $validacao = md5(strtoupper($_POST['codConfirm']) . $_POST['emailVoto']);
                        if ($validacao == $ret[0]->codigo) {
                            $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateSucesso.html");
                            $sql = "update `" . $wpdb->prefix . "wpvotacao` set confirmado = 1 where id = {$ret[0]->id}";
                            $wpdb->query($sql);
                        } else {
                            $htmlVoto = str_replace("#MSG_ERRO#", "Código de confirmaão inválido.", $htmlVoto);
                        }
                    } else {
                        $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateJaVotou.html");
                    }
                } else {
                    $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateInvalido.html");
                }
            }
            $htmlVoto = str_replace("#MSG_ERRO#", "", $htmlVoto);
        } else {
            if (isset($_REQUEST['vote']))
                $_REQUEST['vote'] = str_replace("#", "", $_REQUEST['vote']);

            if (isset($_REQUEST['vote']) && ($_REQUEST['vote'] == 'now')) {

                foreach ($_POST as $key => $value) {
                    $htmlVoto = str_replace("#$key#", $value, $htmlVoto);
                }

                $htmlVoto = str_replace("#BOX_VOTAR#", '', $htmlVoto);
                $erro = false;
                if (strtoupper($_POST['codCaptcha']) != $_SESSION['CAPCHA_' . $postID]) {
                    $erro = true;
                    $htmlVoto = str_replace("#COD_MSG#", 'Código informado está incorreto.', $htmlVoto);
                }
                if (!is_email($_POST['emailVoto'])) {
                    $erro = true;
                    $htmlVoto = str_replace("#EMAIL_MSG#", 'O email informado não é válido.', $htmlVoto);
                }

                //VERIFICA SE O EMAIL JA VOTOU NO POST
                $sql = "select count(1) as qtd from `" . $wpdb->prefix . "wpvotacao` where email = '{$_REQUEST['emailVoto']}' and post_id = {$postID}";
                $ret = $wpdb->get_results($sql);

                if ($ret[0]->qtd > 0) {
                    $erro = true;
                    $htmlVoto = str_replace("#EMAIL_MSG#", 'O email informado já votou.', $htmlVoto);
                }

                //CASO NAO TENHA ERRO ENVIA EMAIL E AGUARDA CONFIRMACAO DO USUARIO
                if (!$erro) {
                    $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateEnviado.html");
                    $htmlVoto = str_replace("#EMAIL#", $_POST['emailVoto'], $htmlVoto);
                    self::enviaEmail($postID, $_POST['emailVoto']);
                }
            } else {

            }
        }
        $htmlVoto = str_replace("#EMAIL_MSG#", '', $htmlVoto);
        $htmlVoto = str_replace("#COD_MSG#", '', $htmlVoto);
        $htmlVoto = str_replace("#emailVoto#", '', $htmlVoto);
        $htmlVoto = str_replace("#BOX_VOTAR#", 'display: none;', $htmlVoto);
        return $htmlVoto;
    }

    /**
     * LISTA AS VOTACOES EM ANDAMENTO
     */
    public static function listaVotacao() {
        global $wpdb;

        $urlExportar = get_bloginfo('url') . "/wp-content/plugins/wp-votacao/exportar.php";

        if(isset($_REQUEST['exportar']) && is_numeric($_REQUEST['exportar'])){
            die("exportar");
        }

        $htmlVoto = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "listaVotacao.html");

        $sql = "select wp.post_id,p.post_title,count(1) as qtd
                from " . $wpdb->prefix . "wpvotacao wp,  " . $wpdb->prefix . "posts p
                where wp.post_id = p.id 
                group by wp.post_id order by p.post_title";

        $ret = $wpdb->get_results($sql);

        $body = "";
        if ($ret) {
            for ($i = 0; $i < count($ret); $i++) {
                $conf = $nconf = $ultVoto = 0;
                $sql = "select count(1) as qtd from " . $wpdb->prefix . "wpvotacao where confirmado = 1 and post_id = {$ret[$i]->post_id}";
                $retSql = $wpdb->get_results($sql);
                if ($retSql)
                    $conf = $retSql[0]->qtd;
                else
                    $conf = 0;

                $sql = "select count(1) as qtd from " . $wpdb->prefix . "wpvotacao where confirmado = 0 and post_id = {$ret[$i]->post_id}";
                $retSql = $wpdb->get_results($sql);
                if ($retSql)
                    $nconf = $retSql[0]->qtd;
                else
                    $nconf = 0;

                $sql = "select max(data_voto) as qtd from " . $wpdb->prefix . "wpvotacao where post_id = {$ret[$i]->post_id}";
                $retSql = $wpdb->get_results($sql);
                if ($retSql) {
                    $ultVoto = $retSql[0]->qtd;
                    $ultVoto = date("d/m/Y H:i:s", strtotime($ultVoto));
                }else
                    $ultVoto = '-';

                $body .= "<tr>";
                $body .= "<td><a class='row-title' target='_blank'>{$ret[$i]->post_title}<a></td>";
                $body .= "<td style=\"text-align: center\"><a class='row-title' style='color:green'>{$conf}<a></td>";
                $body .= "<td style=\"text-align: center\"><a class='row-title' style='color:red'>{$nconf}<a></td>";
                $body .= "<td style=\"text-align: center\"><a class='row-title'>{$ret[$i]->qtd}<a></td>";
                $body .= "<td style=\"text-align: center\"><a class='row-title'>{$ultVoto}<a></td>";
                $body .= "<td><a href='{$urlExportar}?id={$ret[$i]->post_id}'>Exportar<a></td>";
                $body .= "</tr>";
            }
        }

        echo $htmlVoto = str_replace("#TBODY#", $body, $htmlVoto);
    }

    /**
     * ENVIA O EMAIL PARA A CONFIRMACAO DO VOTO
     * @global object $wpdb
     * @param integer $postID
     * @param string $email 
     */
    public static function enviaEmail($postID, $email) {
        global $wpdb;
        if (!empty($email)) {


            //GERA O CODIGO DE CONFIRMACAO
            $cod = chr(rand(65, 78)) . chr(rand(65, 78)) . chr(rand(65, 78)) . rand(1, 9) . rand(1, 9) . rand(1, 0);

            $codVote = md5("{$cod}{$email}");

            //INSERE O REGISTRO E FICA AGUARDANDO CONFIRMACAO
            $wpdb->insert($wpdb->prefix . 'wpvotacao',
                    array("post_id" => $postID,
                        "email" => $email,
                        "codigo" => $codVote,
                        "data_voto" => date("Y-m-d H:i:s"),
                        "confirmado" => '0',)
            );

            //REMOVE A SESSAO
            unset($_SESSION['CAPCHA_' . $postID]);

            $blog = get_bloginfo('name');

            $html = file_get_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "templateEmail.html");
            $html = str_replace("#EMAIL#", $email, $html);
            $html = str_replace("#LINK_VOTACAO#", get_permalink(), $html);
            $html = str_replace("#CODVOTE#", $codVote, $html);
            $html = str_replace("#CODIGO#", $cod, $html);

            $header .= "MIME-Version: 1.0" . "\r\n";
            $header .= "Content-type: text/html; charset=utf-8" . "\r\n";
            mail($email, utf8_decode("Confirmação de Voto - {$blog}"), $html, $header);
        }
    }
}

$votacao = new WpVotacao();

// FUNCAO DE INSTALACAO
register_activation_hook(__FILE__, array($votacao, 'instalar'));

// FUNCAO DE REMOCAO
register_deactivation_hook(__FILE__, array($votacao, 'desinstalar'));

//FUNCAO DE INICIALIZACAO
add_filter('init', array($votacao, 'inicializar'));
add_shortcode('post-votacao', array($votacao, 'geraVotacao'));
?>
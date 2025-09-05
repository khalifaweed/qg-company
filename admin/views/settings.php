<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Configurações do WhatJobs Feeder', 'whatjobs-feeder'); ?></h1>

    <?php settings_errors('whatjobs_feeder_settings'); ?>

    <form method="post" action="">
        <?php wp_nonce_field('whatjobs_feeder_settings'); ?>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="whatjobs_feeder_publisher_id"><?php _e('Publisher ID *', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="whatjobs_feeder_publisher_id" 
                               id="whatjobs_feeder_publisher_id" 
                               value="<?php echo esc_attr(\WhatJobsFeeder\Core::get_option('publisher_id')); ?>" 
                               class="regular-text" 
                               required>
                        <p class="description">
                            <?php 
                            printf(
                                __('Seu Publisher ID do WhatJobs. %s para obter um.', 'whatjobs-feeder'),
                                '<a href="https://publisher.whatjobs.com/publisher/register" target="_blank">' . __('Registre-se aqui', 'whatjobs-feeder') . '</a>'
                            );
                            ?>
                        </p>
                        <button type="button" id="test-connection" class="button button-secondary">
                            <?php _e('Testar Conexão', 'whatjobs-feeder'); ?>
                        </button>
                        <span id="connection-status"></span>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="whatjobs_feeder_default_status"><?php _e('Status padrão das vagas', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="whatjobs_feeder_default_status" id="whatjobs_feeder_default_status">
                            <option value="publish" <?php selected(\WhatJobsFeeder\Core::get_option('default_status'), 'publish'); ?>>
                                <?php _e('Publicado', 'whatjobs-feeder'); ?>
                            </option>
                            <option value="draft" <?php selected(\WhatJobsFeeder\Core::get_option('default_status'), 'draft'); ?>>
                                <?php _e('Rascunho', 'whatjobs-feeder'); ?>
                            </option>
                            <option value="pending" <?php selected(\WhatJobsFeeder\Core::get_option('default_status'), 'pending'); ?>>
                                <?php _e('Pendente', 'whatjobs-feeder'); ?>
                            </option>
                        </select>
                        <p class="description"><?php _e('Status que será atribuído às vagas importadas por padrão.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="whatjobs-info-box">
            <h3><?php _e('Sobre o WhatJobs', 'whatjobs-feeder'); ?></h3>
            <p><?php _e('O WhatJobs é um mecanismo de busca de empregos que agrega vagas de várias fontes. Para usar este plugin, você precisa de um Publisher ID gratuito.', 'whatjobs-feeder'); ?></p>
            
            <h4><?php _e('Como obter seu Publisher ID:', 'whatjobs-feeder'); ?></h4>
            <ol>
                <li><?php _e('Acesse o site do WhatJobs Publisher', 'whatjobs-feeder'); ?>: <a href="https://publisher.whatjobs.com/publisher/register" target="_blank">https://publisher.whatjobs.com/publisher/register</a></li>
                <li><?php _e('Registre-se gratuitamente', 'whatjobs-feeder'); ?></li>
                <li><?php _e('Após o login, encontre seu Publisher ID no painel', 'whatjobs-feeder'); ?></li>
                <li><?php _e('Cole o ID no campo acima e teste a conexão', 'whatjobs-feeder'); ?></li>
            </ol>

            <h4><?php _e('Parâmetros da API:', 'whatjobs-feeder'); ?></h4>
            <ul>
                <li><strong>publisher:</strong> <?php _e('Seu Publisher ID (obrigatório)', 'whatjobs-feeder'); ?></li>
                <li><strong>user_ip:</strong> <?php _e('IP do visitante (adicionado automaticamente)', 'whatjobs-feeder'); ?></li>
                <li><strong>user_agent:</strong> <?php _e('User agent (adicionado automaticamente)', 'whatjobs-feeder'); ?></li>
                <li><strong>keyword:</strong> <?php _e('Palavra-chave para busca', 'whatjobs-feeder'); ?></li>
                <li><strong>location:</strong> <?php _e('Localização para busca', 'whatjobs-feeder'); ?></li>
                <li><strong>limit:</strong> <?php _e('Número máximo de resultados', 'whatjobs-feeder'); ?></li>
                <li><strong>page:</strong> <?php _e('Página dos resultados', 'whatjobs-feeder'); ?></li>
            </ul>
        </div>

        <?php submit_button(__('Salvar Configurações', 'whatjobs-feeder')); ?>
    </form>
</div>

<style>
.whatjobs-info-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
    max-width: 800px;
}

.whatjobs-info-box h3 {
    margin-top: 0;
}

.whatjobs-info-box h4 {
    margin-top: 20px;
    margin-bottom: 10px;
}

.whatjobs-info-box ul,
.whatjobs-info-box ol {
    padding-left: 20px;
}

.whatjobs-info-box li {
    margin-bottom: 5px;
}

#connection-status {
    margin-left: 10px;
    font-weight: bold;
}

#connection-status.success {
    color: #46b450;
}

#connection-status.error {
    color: #dc3232;
}
</style>
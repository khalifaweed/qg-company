<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_edit = isset($feed) && !empty($feed);
$feed_id = $is_edit ? $feed['id'] : 0;

// Default values
$defaults = [
    'keyword' => '',
    'location' => '',
    'limit_jobs' => 10,
    'page_number' => 1,
    'author_id' => get_current_user_id(),
    'job_category' => '',
    'job_type' => '',
    'frequency' => '24h',
    'status' => 'active'
];

$feed_data = $is_edit ? wp_parse_args($feed, $defaults) : $defaults;

// Get users for author dropdown
$users = get_users(['role__in' => ['administrator', 'editor', 'author']]);

// Get job categories
$job_categories = get_terms([
    'taxonomy' => 'job_listing_category',
    'hide_empty' => false
]);

// Get job types
$job_types = get_terms([
    'taxonomy' => 'job_listing_type',
    'hide_empty' => false
]);
?>

<div class="wrap">
    <h1><?php echo $is_edit ? __('Editar Feed', 'whatjobs-feeder') : __('Novo Feed', 'whatjobs-feeder'); ?></h1>

    <form method="post" action="<?php echo admin_url('admin.php?page=whatjobs-feeder'); ?>">
        <?php wp_nonce_field('whatjobs_feeder_save_feed'); ?>
        <input type="hidden" name="action" value="create_feed">
        <?php if ($is_edit): ?>
            <input type="hidden" name="feed_id" value="<?php echo $feed_id; ?>">
        <?php endif; ?>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="source"><?php _e('Fonte', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="source" id="source" disabled>
                            <option value="whatjobs"><?php _e('WhatJobs', 'whatjobs-feeder'); ?></option>
                        </select>
                        <input type="hidden" name="source" value="whatjobs">
                        <p class="description"><?php _e('Atualmente apenas WhatJobs é suportado. Mais fontes serão adicionadas no futuro.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="keyword"><?php _e('Palavra-chave', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="keyword" id="keyword" value="<?php echo esc_attr($feed_data['keyword']); ?>" class="regular-text">
                        <p class="description"><?php _e('Palavra-chave para buscar vagas (ex: "vendas", "programador").', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="location"><?php _e('Localização', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="location" id="location" value="<?php echo esc_attr($feed_data['location']); ?>" class="regular-text">
                        <p class="description"><?php _e('Localização para buscar vagas (ex: "São Paulo", "Rio de Janeiro").', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="limit_jobs"><?php _e('Limite de vagas', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="limit_jobs" id="limit_jobs" value="<?php echo esc_attr($feed_data['limit_jobs']); ?>" min="1" max="100" class="small-text">
                        <p class="description"><?php _e('Número máximo de vagas por execução (1-100).', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="page_number"><?php _e('Página', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="page_number" id="page_number" value="<?php echo esc_attr($feed_data['page_number']); ?>" min="1" class="small-text">
                        <p class="description"><?php _e('Página dos resultados para importar.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="author_id"><?php _e('Autor das vagas', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="author_id" id="author_id">
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user->ID; ?>" <?php selected($feed_data['author_id'], $user->ID); ?>>
                                    <?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Usuário que será definido como autor das vagas importadas.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="job_category"><?php _e('Categoria da vaga', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="job_category" id="job_category">
                            <option value=""><?php _e('Selecione uma categoria', 'whatjobs-feeder'); ?></option>
                            <?php foreach ($job_categories as $category): ?>
                                <option value="<?php echo $category->slug; ?>" <?php selected($feed_data['job_category'], $category->slug); ?>>
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Categoria que será atribuída às vagas importadas.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="job_type"><?php _e('Tipo da vaga', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="job_type" id="job_type">
                            <option value=""><?php _e('Selecione um tipo', 'whatjobs-feeder'); ?></option>
                            <?php foreach ($job_types as $type): ?>
                                <option value="<?php echo $type->slug; ?>" <?php selected($feed_data['job_type'], $type->slug); ?>>
                                    <?php echo esc_html($type->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php _e('Tipo que será atribuído às vagas importadas.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="frequency"><?php _e('Frequência de execução', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="frequency" id="frequency">
                            <option value="1h" <?php selected($feed_data['frequency'], '1h'); ?>><?php _e('A cada 1 hora', 'whatjobs-feeder'); ?></option>
                            <option value="6h" <?php selected($feed_data['frequency'], '6h'); ?>><?php _e('A cada 6 horas', 'whatjobs-feeder'); ?></option>
                            <option value="12h" <?php selected($feed_data['frequency'], '12h'); ?>><?php _e('A cada 12 horas', 'whatjobs-feeder'); ?></option>
                            <option value="24h" <?php selected($feed_data['frequency'], '24h'); ?>><?php _e('A cada 24 horas', 'whatjobs-feeder'); ?></option>
                        </select>
                        <p class="description"><?php _e('Com que frequência este feed deve ser executado automaticamente.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="status"><?php _e('Status', 'whatjobs-feeder'); ?></label>
                    </th>
                    <td>
                        <select name="status" id="status">
                            <option value="active" <?php selected($feed_data['status'], 'active'); ?>><?php _e('Ativo', 'whatjobs-feeder'); ?></option>
                            <option value="inactive" <?php selected($feed_data['status'], 'inactive'); ?>><?php _e('Inativo', 'whatjobs-feeder'); ?></option>
                        </select>
                        <p class="description"><?php _e('Status do feed. Apenas feeds ativos são executados automaticamente.', 'whatjobs-feeder'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="whatjobs-url-preview">
            <h3><?php _e('Preview da URL', 'whatjobs-feeder'); ?></h3>
            <div id="url-preview" class="code-preview">
                <?php _e('Preencha os campos acima para ver a URL que será gerada.', 'whatjobs-feeder'); ?>
            </div>
        </div>

        <?php submit_button($is_edit ? __('Atualizar Feed', 'whatjobs-feeder') : __('Criar Feed', 'whatjobs-feeder')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function updateUrlPreview() {
        var publisherId = '<?php echo esc_js(\WhatJobsFeeder\Core::get_option('publisher_id')); ?>';
        var keyword = $('#keyword').val();
        var location = $('#location').val();
        var limit = $('#limit_jobs').val();
        var page = $('#page_number').val();
        
        if (!publisherId) {
            $('#url-preview').html('<?php _e('Publisher ID não configurado. Configure nas configurações primeiro.', 'whatjobs-feeder'); ?>');
            return;
        }
        
        var url = 'https://api.whatjobs.com/api/v1/jobs.xml?publisher=' + publisherId;
        url += '&user_ip=<?php echo esc_js(\WhatJobsFeeder\Core::get_user_ip()); ?>';
        url += '&user_agent=' + encodeURIComponent('<?php echo esc_js(\WhatJobsFeeder\Core::get_user_agent()); ?>');
        
        if (keyword) url += '&keyword=' + encodeURIComponent(keyword);
        if (location) url += '&location=' + encodeURIComponent(location);
        if (limit) url += '&limit=' + limit;
        if (page) url += '&page=' + page;
        
        $('#url-preview').html('<code>' + url + '</code>');
    }
    
    $('#keyword, #location, #limit_jobs, #page_number').on('input', updateUrlPreview);
    updateUrlPreview();
});
</script>

<style>
.whatjobs-url-preview {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.code-preview {
    background: #fff;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-family: monospace;
    word-break: break-all;
}

.code-preview code {
    background: none;
    padding: 0;
}
</style>
<?php
declare(strict_types=1);

/**
 * Formulário reutilizável de aula (criar/editar).
 * Definido como função para poder ser incluído várias vezes na mesma página.
 */
if (!function_exists('lesson_form')) {
    /**
     * @param array       $modules     módulos do curso (para o select)
     * @param array|null  $l           aula a editar (null = nova)
     * @param int         $fixedModule módulo pré-selecionado ao criar
     */
    function lesson_form(string $csrf, int $courseId, array $modules, ?array $l = null, int $fixedModule = 0): void
    {
        $providers = ['other' => 'Outro/simulado', 'youtube' => 'YouTube', 'vimeo' => 'Vimeo', 'bunny' => 'Bunny', 'file' => 'Arquivo (mp4)'];
        $statuses = ['published' => 'Publicada', 'draft' => 'Rascunho', 'archived' => 'Arquivada'];
        $dur = (int) ($l['duration_seconds'] ?? 0);
        $selModule = $l !== null ? (int) $l['module_id'] : $fixedModule;
        ?>
        <form method="post" style="margin-top:10px;border-top:1px dashed #E4DED1;padding-top:12px">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="save_lesson">
          <input type="hidden" name="course_id" value="<?= $courseId ?>">
          <?php if ($l !== null): ?><input type="hidden" name="lesson_id" value="<?= (int) $l['id'] ?>"><?php endif; ?>
          <div class="row">
            <div><label>Título</label><input type="text" name="title" value="<?= e($l['title'] ?? '') ?>" required></div>
            <div><label>Módulo</label><select name="module_id">
              <?php foreach ($modules as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= (int)$m['id']===$selModule?'selected':'' ?>><?= e($m['title']) ?></option>
              <?php endforeach; ?>
            </select></div>
          </div>
          <label>Descrição</label><textarea name="description"><?= e($l['description'] ?? '') ?></textarea>
          <div class="row">
            <div><label>URL do vídeo</label><input type="text" name="video_url" value="<?= e($l['video_url'] ?? '') ?>" placeholder="https://..."></div>
            <div style="max-width:160px"><label>Provedor</label><select name="video_provider">
              <?php foreach ($providers as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($l['video_provider'] ?? 'other')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select></div>
          </div>
          <div class="row">
            <div style="max-width:120px"><label>Duração (min)</label><input type="number" name="duration_minutes" min="0" value="<?= intdiv($dur,60) ?>"></div>
            <div style="max-width:120px"><label>(seg)</label><input type="number" name="duration_secs" min="0" max="59" value="<?= $dur%60 ?>"></div>
            <div style="max-width:110px"><label>Ordem</label><input type="number" name="order_index" value="<?= (int) ($l['order_index'] ?? 1) ?>"></div>
            <div style="max-width:150px"><label>Status</label><select name="status">
              <?php foreach ($statuses as $k => $v): ?>
                <option value="<?= $k ?>" <?= ($l['status'] ?? 'published')===$k?'selected':'' ?>><?= $v ?></option>
              <?php endforeach; ?>
            </select></div>
          </div>
          <div style="margin-top:10px"><button class="btn btn-sm" type="submit"><?= $l !== null ? 'Salvar aula' : 'Criar aula' ?></button></div>
        </form>
        <?php
    }
}

<?php

require_once "inc_all_settings.php";
 ?>

<div class="card card-dark">
    <div class="card-header py-3">
        <h3 class="card-title"><i class="fas fa-fw fa-robot mr-2"></i>AI</h3>
    </div>
    <div class="card-body">
        <form action="post.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label>AI Provider</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                    </div>
                    <select class="form-control select2" name="provider">
                        <option value="" <?php if($config_ai_enable == 0) { echo "selected"; } ?> >Disabled</option>
                        <option <?php if($config_ai_provider == "Ollama") { echo "selected"; } ?> >Ollama</option>
                        <option <?php if($config_ai_provider == "OpenAI") { echo "selected"; } ?> >OpenAI</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>URL</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                    </div>
                    <input type="url" class="form-control" name="url" value="<?php echo nullable_htmlentities($config_ai_url); ?>" placeholder="ex https://ai.company.ext/api">
                </div>
            </div>

            <div class="form-group">
                <label>API Key</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-key"></i></span>
                    </div>
                    <input type="text" class="form-control" name="api_key" value="<?php echo nullable_htmlentities($config_ai_api_key); ?>" placeholder="Enter API key here">
                </div>
            </div>

            <hr>

            <button type="submit" name="edit_ai_settings" class="btn btn-primary text-bold"><i class="fa fa-check mr-2"></i>Save</button>

        </form>
    </div>
</div>

<?php
require_once "footer.php";


<?php

require_once "inc_all_admin.php";
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
                        <option <?php if($config_ai_provider == "LocalAI") { echo "selected"; } ?> >LocalAI</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>AI Model</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fa fa-fw fa-robot"></i></span>
                    </div>
                    <input type="text" class="form-control" name="model" value="<?php echo nullable_htmlentities($config_ai_model); ?>" placeholder="ex gpt-4">
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

        <div class="mt-5">
            <h1>Test Input Text to Reword</h1>
            <textarea id="textInput" class="form-control tinymceai mb-3" rows="10"></textarea>
            <button id="rewordButton" class="btn btn-primary"><i class="fas fa-fw fa-robot mr-2"></i>Reword</button>
            <button id="undoButton" class="btn btn-secondary" style="display:none;"><i class="fas fa-fw fa-redo-alt mr-2"></i>Undo</button>
        </div>

        <script src="js/ai_reword.js"></script>

    </div>
</div>

<?php
require_once "footer.php";


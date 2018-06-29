<?php
use yii\helpers\Html;
?>
<?php $this->beginPage() ?>
        <?php $this->head() ?>
    <?php $this->beginBody() ?>
    <?= $content ?>
    <?php $this->endBody() ?>
<?php $this->endPage() ?>
Type of status:

<?php if($f_header): ?>
    <b><?php echo htmlspecialchars($f_header, ENT_QUOTES); ?></b><br/>
<?php endif; ?>
<ul>
<?php foreach($feedback as $notice): ?>
    <li>&emsp;<?php echo $notice; ?></li>
<?php endforeach; ?>
</ul>


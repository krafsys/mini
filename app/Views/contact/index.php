<h1><?= e($title) ?></h1>

<?php if (!empty($sent)): ?>
    <p>Thanks! Your message has been sent.</p>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="/contact">
        <?= csrf_field() ?>

        <label>Name
            <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>">
        </label>

        <label>Email
            <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>">
        </label>

        <label>Message
            <textarea name="message" rows="4"><?= e($old['message'] ?? '') ?></textarea>
        </label>

        <button type="submit">Send</button>
    </form>
<?php endif; ?>

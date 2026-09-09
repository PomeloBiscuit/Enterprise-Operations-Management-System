<?php
require_once __DIR__ . '/../src/config.inc.php';
require_once __DIR__ . '/../src/auth.inc.php';
require_once __DIR__ . '/../src/i18n.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $partyType = $_POST['party_type'] ?? '';
        if (!in_array($partyType, ['廠商', '客戶'], true)) {
            throw new InvalidArgumentException(t('auth.register.err_pick_party'));
        }
        $stmt = $pdo->prepare("
            INSERT INTO User (datechg, dateadd, name, id, pw, phone, phonem, email, enabled, open, status, limited, party_type)
            VALUES (datetime('now','localtime'), datetime('now','localtime'), :name, :id, :pw, '', '', :email, 1, 1, 1, :limited, :party_type)
        ");
        $stmt->execute([
            ':name'  => $_POST['name'],
            ':id'    => $_POST['id'],
            ':pw'    => password_hash($_POST['pw'], PASSWORD_DEFAULT),
            ':email' => $_POST['email'],
            ':limited' => USER_LIMIT_EXTERNAL,
            ':party_type' => $partyType
        ]);
        header("Location: index.php");
        exit();
    } catch (PDOException | InvalidArgumentException $e) {
        echo "<p>" . t('auth.register.err_prefix') . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
?>

<div class="container mt-5">
    <div class="card" style="border-radius: 15px;">
        <div class="card-header text-center">
            <h3><?php echo t('auth.register.title'); ?></h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label><?php echo t('field.name'); ?></label>
                    <input type="text" name="name" class="form-control" placeholder="<?php echo htmlspecialchars(t('auth.register.name_ph')); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('field.account'); ?></label>
                    <input type="text" name="id" class="form-control" placeholder="<?php echo htmlspecialchars(t('auth.register.id_ph')); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('field.password'); ?></label>
                    <input type="password" name="pw" class="form-control" placeholder="<?php echo htmlspecialchars(t('auth.register.pw_ph')); ?>" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('field.email'); ?></label>
                    <input type="email" name="email" class="form-control" placeholder="<?php echo htmlspecialchars(t('auth.register.email_ph')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="party_type"><?php echo t('field.party_type'); ?></label>
                    <select id="party_type" name="party_type" class="form-control" required>
                        <option value="" selected disabled><?php echo t('auth.register.party_ph'); ?></option>
                        <option value="廠商"><?php echo t('party.firm'); ?></option>
                        <option value="客戶"><?php echo t('party.customer'); ?></option>
                    </select>
                </div>
                <br>
                <div class="text-center">
                    <a href="index.php" class="btn btn-secondary"><?php echo t('common.back'); ?></a>
                    <span style='display: inline-block; width: 20px;'></span>
                    <button type="reset" class="btn btn-warning"><?php echo t('common.clear'); ?></button>
                    <span style='display: inline-block; width: 20px;'></span>
                    <button type="submit" class="btn btn-primary"><?php echo t('common.submit'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

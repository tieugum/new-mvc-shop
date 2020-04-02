<?php
// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function user_login($email, $password)
{
    global $linkconnectDB;
    $sql = "SELECT * FROM users WHERE user_email='$email' AND user_password='$password' LIMIT 0,1";
    $query = mysqli_query($linkconnectDB, $sql) or die(mysqli_error($linkconnectDB));
    if (mysqli_num_rows($query) > 0) {
        $_SESSION['user'] = mysqli_fetch_assoc($query);
        global $user_nav;
        $user_nav = $_SESSION['user']['id'];
        return true;
    }
    return false;
}
function user_delete($id)
{
    $user = get_a_record('users', $id);
    $image = 'public/upload/images/' . $user['user_avatar'];
    if (is_file($image)) {
        unlink($image);
    }
    global $linkconnectDB;
    $id = intval($id);
    $sql = "DELETE FROM users WHERE id=$id";
    mysqli_query($linkconnectDB, $sql) or die(mysqli_error($linkconnectDB));
}
function changePassword($id, $newpassword, $currentPassword)
{
    global $linkconnectDB;
    $sql = "Update users SET user_password='$newpassword' WHERE id='$id' AND user_password = '$currentPassword'";
    mysqli_query($linkconnectDB, $sql) or die(mysqli_error($linkconnectDB));
    $rows =  mysqli_affected_rows($linkconnectDB); //Gets the number of affected rows in a previous MySQL operation
    if ($rows <> 1) {
        return  "<div style='padding-top: 200' class='container'><div class='alert alert-danger' style='text-align: center;'><strong>NO!</strong> Việc thay đổi mật khẩu có vấn đề. Bạn đã nhập mật khẩu hiện tại không đúng !! <br><a href='javascript: history.go(-1)'>Trở lại</a> hoặc <a href='admin.php'>Đến Dashboard</a></div></div>" . mysqli_error($linkconnectDB);
    } else {
        $options = array(
            'id' => $id,
            'user_password' => $newpassword
        );
        save('users', $options);
        //sendmail
        require 'vendor/autoload.php';
        include 'lib/config/sendmail.php';
        $mail = new PHPMailer(true);
        $user = get_a_record('users', $id);
        $email = $user['user_email'];
        try {
            //content
            $htmlStr = "";
            $htmlStr .= "Xin chào " . $user['user_username'] . ' (' . $email . "),<br /><br />";
            $htmlStr .= "Mật khẩu của bạn hiện đã được thay đổi cách đây không lâu...<br /><br />";
            $htmlStr .= "Vui lòng kiểm tra và <a href='" . PATH_URL . "admin.php'>Đăng nhập</a></div> lại với mật khẩu mới của bạn.<br><br>";
            $htmlStr .= "Trân trọng,<br />";
            $htmlStr .= "<a href='https://tanhongit.com/' target='_blank'>By Tân Hồng IT</a><br />";
            //Server settings
            $mail->CharSet = "UTF-8";
            $mail->SMTPDebug = 0; // Enable verbose debug output (0 : ko hiện debug, 1 hiện)
            $mail->isSMTP(); // Set mailer to use SMTP
            $mail->Host = SMTP_HOST;  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = SMTP_UNAME; // SMTP username
            $mail->Password = SMTP_PWORD; // SMTP password
            $mail->SMTPSecure = 'ssl'; // Enable TLS encryption, `ssl` also accepted
            $mail->Port = SMTP_PORT; // TCP port to connect to
            //Recipients
            $mail->setFrom(SMTP_UNAME, "Chị Kòi Quán");
            $mail->addAddress($email, $email);     // Add a recipient | name is option tên người nhận
            $mail->addReplyTo(SMTP_UNAME, 'Tân Hồng IT');
            //$mail->addCC('CCemail@gmail.com');
            //$mail->addBCC('BCCemail@gmail.com');
            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = 'You have Change your Password | Quán Chị Kòi | By Tân Hồng IT';
            $mail->Body = $htmlStr;
            $mail->AltBody = $htmlStr; //None HTML
            $result = $mail->send();
            if (!$result) {
                $error = "Có lỗi xảy ra trong quá trình gửi mail";
            }
        } catch (Exception $e) {
            echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
        }
        return '<div style="padding-top: 200" class="container"><div class="alert alert-success" style="text-align: center;"><strong>Tốt!</strong> Bạn đã thay đổi mật khẩu thành công. Và một tin nhắn thông báo đã được gửi đến Email của người dùng này. Hãy <a href="admin.php?controller=home&action=logout">Đăng xuất</a> và đăng nhập lại.!!</div></div>';
    }
}
function user_update()
{
    $user_edit = array(
        'id' => intval($_POST['user_id']),
        'user_email' => escape($_POST['email']),
        'user_username' => escape($_POST['username']),
        'user_name' => escape($_POST['name']),
        'user_address' => escape($_POST['address']),
        'user_phone' => escape($_POST['phone'])
    );
    global $linkconnectDB;
    $email_check = addslashes($_POST['email']);
    if (mysqli_num_rows(mysqli_query($linkconnectDB, "SELECT user_email FROM users WHERE user_email='$email_check'")) > 0) {
        echo "<div style='padding-top: 200' class='container'><div class='alert alert-danger' style='text-align: center;'><strong>NO!</strong> Email này đã có người dùng. Vui lòng chọn Email khác. <a href='javascript: history.go(-1)'>Trở lại</a></div></div>";
        require('admin/views/user/result.php');
    } else {
        $get_currentEmail_user = get_a_record('users', $_POST['user_id']);
        $currentEmail = $get_currentEmail_user['user_email'];
        $user_id =  save('users', $user_edit);
        $avatar_name = 'avatar-user' . $user_id . '-' . slug($_POST['username']);
        $config = array(
            'name' => $avatar_name,
            'upload_path'  => 'public/upload/images/',
            'allowed_exts' => 'jpg|jpeg|png|gif',
        );
        $avatar = upload('imagee', $config);
        //cập nhật ảnh mới
        if ($avatar) {
            $user_edit = array(
                'id' => $user_id,
                'user_avatar' => $avatar
            );
            save('users', $user_edit);
        }
        $user_edited = get_a_record('users', $user_id);
        if ($user_edited['user_email'] != $currentEmail) {
            //send mail
            require 'vendor/autoload.php';
            include 'lib/config/sendmail.php';
            $email = $user_edited['user_email'];
            $mail = new PHPMailer(true);
            try {
                $verificationCode = md5(uniqid("Email của bạn vừa mới đổi đó và chưa active đâu. Nhấn vào đây để active nhé! Yêu bạn 3 nghìn", true)); //https://www.php.net/manual/en/function.uniqid
                $verificationLink = PATH_URL . "index.php?controller=register&action=reactivate&code=" . $verificationCode;
                //content
                $htmlStr = "";
                $htmlStr .= "Xin chào " . $user_edited['user_name'] . " (" . $user_edited['user_username']  . "),<br /><br />";
                $htmlStr .= "Bạn vừa đổi email mới cho tài khoản của bạn? Vui lòng nhấp vào nút bên dưới để xác minh đổi email của bạn và có quyền truy cập vào trang quản trị của Chị Kòi Quán.<br /><br /><br />";
                $htmlStr .= "<a href='{$verificationLink}' target='_blank' style='padding:1em; font-weight:bold; background-color:blue; color:#fff;'>VERIFY EMAIL</a><br /><br /><br />";
                $htmlStr .= "Cảm ơn bạn đã tham gia cùng website bán hàng của quán Chị Kòi.<br><br>";
                $htmlStr .= "Trân trọng,<br />";
                $htmlStr .= "<a href='https://tanhongit.com/' target='_blank'>By Tân Hồng IT</a><br />";
                //Server settings
                $mail->CharSet = "UTF-8";
                $mail->SMTPDebug = 0; // Enable verbose debug output (0 : ko hiện debug, 1 hiện)
                $mail->isSMTP(); // Set mailer to use SMTP
                $mail->Host = SMTP_HOST;  // Specify main and backup SMTP servers
                $mail->SMTPAuth = true; // Enable SMTP authentication
                $mail->Username = SMTP_UNAME; // SMTP username
                $mail->Password = SMTP_PWORD; // SMTP password
                $mail->SMTPSecure = 'ssl'; // Enable TLS encryption, `ssl` also accepted
                $mail->Port = SMTP_PORT; // TCP port to connect to
                //Recipients
                $mail->setFrom(SMTP_UNAME, "Chị Kòi Quán");
                $mail->addAddress($email, $email);     // Add a recipient | name is option tên người nhận
                $mail->addReplyTo(SMTP_UNAME, 'Tên người trả lời');
                //$mail->addCC('CCemail@gmail.com');
                //$mail->addBCC('BCCemail@gmail.com');
                $mail->isHTML(true); // Set email format to HTML
                $mail->Subject = 'Verification New Email | Quán Chị Kòi | Change Email | By Tân Hồng IT';
                $mail->Body = $htmlStr;
                $mail->AltBody = $htmlStr; //None HTML
                $result = $mail->send();
                if (!$result) {
                    $error = "Có lỗi xảy ra trong quá trình gửi mail";
                }
            } catch (Exception $e) {
                echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
            }
            $verificationCode_add = array(
                'id' => $user_id,
                'verificationCode' => $verificationCode,
                'verified' => 0
            );
            save('users', $verificationCode_add);
        }
        header('location:admin.php?controller=user&action=info&user_id=' . intval($_POST['user_id']));
    }
}
function user_add()
{
    $user_add = array(
        'id' => intval($_POST['user_id']),
        'user_username' => escape($_POST['username']),
        'user_password' => md5($_POST['password']),
        'user_email' => escape($_POST['email']),
        'role_id' => escape($_POST['roleid']),
        'user_name' => escape($_POST['name']),
        'user_address' => escape($_POST['address']),
        'createDate' => gmdate('Y-m-d H:i:s', time() + 7 * 3600),
        'user_phone' => escape($_POST['phone'])
    );
    global $linkconnectDB;
    $username = addslashes($_POST['username']);
    $email = addslashes($_POST['email']);
    //https://freetuts.net/xay-dung-chuc-nang-dang-nhap-va-dang-ky-voi-php-va-mysql-85.html
    if (mysqli_num_rows(mysqli_query($linkconnectDB, "SELECT user_username FROM users WHERE user_username='$username'")) > 0) {
        echo "";
        "<div style='padding-top: 200' class='container'><div class='alert alert-danger' style='text-align: center;'><strong>NO!</strong> Tên đăng nhập này đã có người dùng. Vui lòng chọn tên đăng nhập khác. <a href='javascript: history.go(-1)'>Trở lại</a></div></div>";
        require('admin/views/user/addresult.php');
        exit;
    } elseif (!preg_match("/([a-z0-9_]+|[a-z0-9_]+\.[a-z0-9_]+)@(([a-z0-9]|[a-z0-9]+\.[a-z0-9]+)+\.([a-z]{2,4}))/i", $email)) {
        echo "<div style='padding-top: 200' class='container'><div class='alert alert-danger' style='text-align: center;'><strong>NO!</strong> Email này không hợp lệ. Vui long nhập email khác. <a href='javascript: history.go(-1)'>Trở lại</a></div></div>";
        require('admin/views/user/addresult.php');
        exit;
    } elseif (strlen($_POST['password']) < 8) {
        echo "<div style='padding-top: 200' class='container'><div style='text-align: center;' class='alert alert-danger'><strong>NO!</strong> Mật khẩu bạn nhập phải dài từ 8 ký tự trở lên !! <br><a href='javascript: history.go(-1)'>Trở lại</a></div></div>";
    } elseif (mysqli_num_rows(mysqli_query($linkconnectDB, "SELECT user_email FROM users WHERE user_email='$email'")) > 0) {
        echo "<div style='padding-top: 200' class='container'><div class='alert alert-danger' style='text-align: center;'><strong>NO!</strong> Email này đã có người dùng. Vui lòng chọn Email khác. <a href='javascript: history.go(-1)'>Trở lại</a></div></div>";
        require('admin/views/user/addresult.php');
        exit;
    } else {
        $user_id =  save('users', $user_add);
        $avatar_name = 'avatar-user' . $user_id . '-' . slug($_POST['username']);
        $config = array(
            'name' => $avatar_name,
            'upload_path'  => 'public/upload/images/',
            'allowed_exts' => 'jpg|jpeg|png|gif',
        );
        $avatar = upload('imagee', $config);
        if ($avatar) {
            $user_add = array(
                'id' => $user_id,
                'user_avatar' => $avatar
            );
            save('users', $user_add);
        }
        //send mail
        require 'vendor/autoload.php';
        include 'lib/config/sendmail.php';
        $mail = new PHPMailer(true);
        try {
            $verificationCode = md5(uniqid("Email của bạn vừa mới đổi đó và chưa active đâu. Nhấn vào đây để active nhé! Yêu bạn 3 nghìn", true)); //https://www.php.net/manual/en/function.uniqid
            $verificationLink = PATH_URL . "index.php?controller=register&action=activate&code=" . $verificationCode;
            //content
            $htmlStr = "";
            $htmlStr .= "Xin chào " . $email . "),<br /><br />";
            $htmlStr .= "Vui lòng nhấp vào nút bên dưới để xác minh đăng ký của bạn và có quyền truy cập vào trang quản trị của Chị Kòi Quán.<br /><br /><br />";
            $htmlStr .= "<a href='{$verificationLink}' target='_blank' style='padding:1em; font-weight:bold; background-color:blue; color:#fff;'>VERIFY EMAIL</a><br /><br /><br />";
            $htmlStr .= "Cảm ơn bạn đã tham gia thành một thành viên mới trong website bán hàng của quán Chị Kòi.<br><br>";
            $htmlStr .= "Trân trọng,<br />";
            $htmlStr .= "<a href='https://tanhongit.com/' target='_blank'>By Tân Hồng IT</a><br />";
            //Server settings
            $mail->CharSet = "UTF-8";
            $mail->SMTPDebug = 0; // Enable verbose debug output (0 : ko hiện debug, 1 hiện)
            $mail->isSMTP(); // Set mailer to use SMTP
            $mail->Host = SMTP_HOST;  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = SMTP_UNAME; // SMTP username
            $mail->Password = SMTP_PWORD; // SMTP password
            $mail->SMTPSecure = 'ssl'; // Enable TLS encryption, `ssl` also accepted
            $mail->Port = SMTP_PORT; // TCP port to connect to
            //Recipients
            $mail->setFrom(SMTP_UNAME, "Chị Kòi Quán");
            $mail->addAddress($email, $email);     // Add a recipient | name is option tên người nhận
            $mail->addReplyTo(SMTP_UNAME, 'Tên người trả lời');
            //$mail->addCC('CCemail@gmail.com');
            //$mail->addBCC('BCCemail@gmail.com');
            $mail->isHTML(true); // Set email format to HTML
            $mail->Subject = 'Verification Users | Quán Chị Kòi | Subscription | By Tân Hồng IT';
            $mail->Body = $htmlStr;
            $mail->AltBody = $htmlStr; //None HTML
            $result = $mail->send();
            if (!$result) {
                $error = "Có lỗi xảy ra trong quá trình gửi mail";
            }
        } catch (Exception $e) {
            echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
        }
        $verificationCode_add = array(
            'id' => $user_id,
            'verificationCode' => $verificationCode,
            'verified' => 0
        );
        save('users', $verificationCode_add);
        header('location:admin.php?controller=user&action=info&user_id=' . $user_id);
    }
}

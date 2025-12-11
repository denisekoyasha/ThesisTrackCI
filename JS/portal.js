function selectRole(role) {
    switch(role) {
        case 'student':
            window.location.href = 'student_login.php';
            break;
        case 'advisor':
            window.location.href = 'advisor_login.php';
            break;
        case 'coordinator':
            window.location.href = 'coordinator_login.php';
            break;
        default:
            alert('Invalid role selected');
    }
}

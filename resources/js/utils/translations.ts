export const translateAuthError = (error?: string): string => {
    if (!error) return '';
    const lowerError = error.toLowerCase();

    if (lowerError.includes("we can't find a user") || lowerError.includes("passwords.user")) {
        return 'Não encontramos um usuário com este endereço de e-mail.';
    }
    if (lowerError.includes('token is invalid') || lowerError.includes('passwords.token')) {
        return 'Este token de redefinição de senha é inválido.';
    }
    if (lowerError.includes('already been taken')) {
        return 'Este e-mail já está em uso.';
    }
    if (lowerError.includes('confirmation does not match')) {
        return 'A confirmação da senha não confere.';
    }
    if (lowerError.includes('at least 8 characters')) {
        return 'A senha deve ter pelo menos 8 caracteres.';
    }
    if (lowerError.includes('credentials do not match') || lowerError.includes('auth.failed')) {
        return 'As credenciais informadas não correspondem aos nossos registros.';
    }
    if (lowerError.includes('too many login attempts') || lowerError.includes('auth.throttle')) {
        return 'Muitas tentativas de login. Tente novamente mais tarde.';
    }
    if (lowerError.includes('required')) {
        if (lowerError.includes('email')) return 'O e-mail é obrigatório.';
        if (lowerError.includes('password') || lowerError.includes('senha')) return 'A senha é obrigatória.';
        if (lowerError.includes('name') || lowerError.includes('nome')) return 'O nome é obrigatório.';
        return 'Este campo é obrigatório.';
    }
    if (lowerError.includes('valid email')) {
        return 'Informe um endereço de e-mail válido.';
    }

    return error;
};

export const translateAuthStatus = (status?: string): string => {
    if (!status) return '';

    if (status === 'We have emailed your password reset link.' || status === 'passwords.sent') {
        return 'Enviamos um link para redefinir sua senha.';
    }
    if (status === 'Your password has been reset.' || status === 'passwords.reset') {
        return 'Senha redefinida com sucesso.';
    }

    return status;
};

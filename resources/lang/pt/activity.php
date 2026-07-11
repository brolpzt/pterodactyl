<?php

return [
    'auth' => [
        'fail' => 'Falha no início de sessão',
        'success' => 'Início de sessão',
        'password-reset' => 'Palavra-passe reposta',
        'reset-password' => 'Pedido de reposição de palavra-passe',
        'checkpoint' => 'Autenticação de dois fatores solicitada',
        'recovery-token' => 'Utilizado token de recuperação de dois fatores',
        'token' => 'Desbloqueio de dois fatores resolvido',
        'ip-blocked' => 'Pedido bloqueado de endereço IP não listado para :identifier',
        'sftp' => [
            'fail' => 'Falha no início de sessão SFTP',
        ],
    ],
    'user' => [
        'account' => [
            'email-changed' => 'E-mail alterado de :old para :new',
            'password-changed' => 'Palavra-passe alterada',
        ],
        'api-key' => [
            'create' => 'Criada nova chave API :identifier',
            'delete' => 'Eliminada chave API :identifier',
        ],
        'ssh-key' => [
            'create' => 'Adicionada chave SSH :fingerprint à conta',
            'delete' => 'Removida chave SSH :fingerprint da conta',
        ],
        'two-factor' => [
            'create' => 'Autenticação de dois fatores ativada',
            'delete' => 'Autenticação de dois fatores desativada',
        ],
    ],
    'server' => [
        'reinstall' => 'Reinstalação do servidor',
        'console' => [
            'command' => 'Executado ":command" no servidor',
        ],
        'power' => [
            'start' => 'Servidor iniciado',
            'stop' => 'Servidor parado',
            'restart' => 'Servidor reiniciado',
            'kill' => 'Processo do servidor terminado',
        ],
        'backup' => [
            'download' => 'Backup :name transferido',
            'delete' => 'Backup :name eliminado',
            'restore' => 'Backup :name restaurado (ficheiros eliminados: :truncate)',
            'restore-complete' => 'Restauro do backup :name concluído',
            'restore-failed' => 'Falha ao concluir o restauro do backup :name',
            'start' => 'Iniciado novo backup :name',
            'complete' => 'Backup :name marcado como concluído',
            'fail' => 'Backup :name marcado como falhado',
            'lock' => 'Backup :name bloqueado',
            'unlock' => 'Backup :name desbloqueado',
        ],
        'database' => [
            'create' => 'Criada nova base de dados :name',
            'rotate-password' => 'Palavra-passe da base de dados :name alterada',
            'delete' => 'Base de dados :name eliminada',
        ],
        'file' => [
            'compress_one' => 'Comprimido :directory:file',
            'compress_other' => 'Comprimidos :count ficheiros em :directory',
            'read' => 'Visualizado o conteúdo de :file',
            'copy' => 'Criada cópia de :file',
            'create-directory' => 'Criado diretório :directory:name',
            'decompress' => 'Descomprimidos :files em :directory',
            'delete_one' => 'Eliminado :directory:files.0',
            'delete_other' => 'Eliminados :count ficheiros em :directory',
            'download' => 'Transferido :file',
            'pull' => 'Transferido ficheiro remoto de :url para :directory',
            'rename_one' => 'Renomeado :directory:files.0.from para :directory:files.0.to',
            'rename_other' => 'Renomeados :count ficheiros em :directory',
            'write' => 'Escrito novo conteúdo em :file',
            'upload' => 'Iniciado envio de ficheiro',
            'uploaded' => 'Enviado :directory:file',
        ],
        'sftp' => [
            'denied' => 'Acesso SFTP bloqueado por permissões',
            'create_one' => 'Criado :files.0',
            'create_other' => 'Criados :count ficheiros',
            'write_one' => 'Alterado o conteúdo de :files.0',
            'write_other' => 'Alterado o conteúdo de :count ficheiros',
            'delete_one' => 'Eliminado :files.0',
            'delete_other' => 'Eliminados :count ficheiros',
            'create-directory_one' => 'Criado o diretório :files.0',
            'create-directory_other' => 'Criados :count diretórios',
            'rename_one' => 'Renomeado :files.0.from para :files.0.to',
            'rename_other' => 'Renomeados ou movidos :count ficheiros',
        ],
        'allocation' => [
            'create' => 'Adicionada :allocation ao servidor',
            'notes' => 'Notas de :allocation atualizadas de ":old" para ":new"',
            'primary' => 'Definida :allocation como alocação principal',
            'delete' => 'Eliminada alocação :allocation',
        ],
        'schedule' => [
            'create' => 'Criado agendamento :name',
            'update' => 'Atualizado agendamento :name',
            'execute' => 'Executado manualmente o agendamento :name',
            'delete' => 'Eliminado agendamento :name',
        ],
        'task' => [
            'create' => 'Criada tarefa ":action" para o agendamento :name',
            'update' => 'Atualizada tarefa ":action" do agendamento :name',
            'delete' => 'Eliminada tarefa do agendamento :name',
        ],
        'settings' => [
            'rename' => 'Servidor renomeado de :old para :new',
            'description' => 'Descrição do servidor alterada de :old para :new',
        ],
        'startup' => [
            'edit' => 'Variável :variable alterada de ":old" para ":new"',
            'image' => 'Imagem Docker do servidor atualizada de :old para :new',
        ],
        'subuser' => [
            'create' => 'Adicionado :email como subutilizador',
            'update' => 'Permissões do subutilizador :email atualizadas',
            'delete' => 'Removido :email como subutilizador',
        ],
        'amxx' => [
            'admin' => [
                'create' => 'Admin AMXX :auth criado (:auth_type)',
                'update' => 'Admin AMXX #:admin_id atualizado',
                'delete' => 'Admin AMXX #:admin_id removido',
            ],
            'player' => [
                'kick' => 'Kick no jogador #:userid via AMXX Web',
                'slap' => 'Slap no jogador #:userid (:damage dano) via AMXX Web',
                'slay' => 'Slay no jogador #:userid via AMXX Web',
            ],
            'ban' => [
                'create' => 'Ban a :identifier (:type) por :minutes minutos via AMXX Web',
                'delete' => 'Ban AMXX :ban_id removido',
            ],
            'map' => [
                'change' => 'Mapa alterado para :map via AMXX Web',
            ],
            'chat' => [
                'say' => 'Mensagem admin via AMXX Web: :message',
                'psay' => 'Mensagem privada para #:userid via AMXX Web: :message',
            ],
            'cvar' => [
                'set' => ':name definido para :value via AMXX Web',
            ],
        ],
    ],
];

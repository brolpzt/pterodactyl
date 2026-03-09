<?php

return [
    'auth' => [
        'fail' => 'Error al iniciar sesión',
        'success' => 'Sesión iniciada',
        'password-reset' => 'Contraseña restablecida',
        'reset-password' => 'Solicitud de restablecimiento de contraseña',
        'checkpoint' => 'Autenticación de dos factores solicitada',
        'recovery-token' => 'Usado token de recuperación de dos factores',
        'token' => 'Desafío de dos factores resuelto',
        'ip-blocked' => 'Solicitud bloqueada desde IP no permitida para :identifier',
        'sftp' => [
            'fail' => 'Error al iniciar sesión SFTP',
        ],
    ],
    'user' => [
        'account' => [
            'email-changed' => 'Correo cambiado de :old a :new',
            'password-changed' => 'Contraseña cambiada',
        ],
        'api-key' => [
            'create' => 'Creada nueva clave API :identifier',
            'delete' => 'Eliminada clave API :identifier',
        ],
        'ssh-key' => [
            'create' => 'Añadida clave SSH :fingerprint a la cuenta',
            'delete' => 'Eliminada clave SSH :fingerprint de la cuenta',
        ],
        'two-factor' => [
            'create' => 'Autenticación de dos factores activada',
            'delete' => 'Autenticación de dos factores desactivada',
        ],
    ],
    'server' => [
        'reinstall' => 'Servidor reinstalado',
        'console' => [
            'command' => 'Ejecutado ":command" en el servidor',
        ],
        'power' => [
            'start' => 'Servidor iniciado',
            'stop' => 'Servidor detenido',
            'restart' => 'Servidor reiniciado',
            'kill' => 'Proceso del servidor terminado',
        ],
        'backup' => [
            'download' => 'Backup :name descargado',
            'delete' => 'Backup :name eliminado',
            'restore' => 'Backup :name restaurado (archivos eliminados: :truncate)',
            'restore-complete' => 'Restauración del backup :name completada',
            'restore-failed' => 'Error al completar la restauración del backup :name',
            'start' => 'Iniciado nuevo backup :name',
            'complete' => 'Backup :name marcado como completado',
            'fail' => 'Backup :name marcado como fallido',
            'lock' => 'Backup :name bloqueado',
            'unlock' => 'Backup :name desbloqueado',
        ],
        'database' => [
            'create' => 'Creada nueva base de datos :name',
            'rotate-password' => 'Contraseña de la base de datos :name rotada',
            'delete' => 'Base de datos :name eliminada',
        ],
        'file' => [
            'compress_one' => 'Comprimido :directory:file',
            'compress_other' => 'Comprimidos :count archivos en :directory',
            'read' => 'Visto el contenido de :file',
            'copy' => 'Creada copia de :file',
            'create-directory' => 'Creado directorio :directory:name',
            'decompress' => 'Descomprimidos :files en :directory',
            'delete_one' => 'Eliminado :directory:files.0',
            'delete_other' => 'Eliminados :count archivos en :directory',
            'download' => 'Descargado :file',
            'pull' => 'Descargado archivo remoto de :url a :directory',
            'rename_one' => 'Renombrado :directory:files.0.from a :directory:files.0.to',
            'rename_other' => 'Renombrados :count archivos en :directory',
            'write' => 'Escrito nuevo contenido en :file',
            'upload' => 'Iniciada subida de archivo',
            'uploaded' => 'Subido :directory:file',
        ],
        'sftp' => [
            'denied' => 'Acceso SFTP bloqueado por permisos',
            'create_one' => 'Creado :files.0',
            'create_other' => 'Creados :count archivos',
            'write_one' => 'Modificado el contenido de :files.0',
            'write_other' => 'Modificado el contenido de :count archivos',
            'delete_one' => 'Eliminado :files.0',
            'delete_other' => 'Eliminados :count archivos',
            'create-directory_one' => 'Creado el directorio :files.0',
            'create-directory_other' => 'Creados :count directorios',
            'rename_one' => 'Renombrado :files.0.from a :files.0.to',
            'rename_other' => 'Renombrados o movidos :count archivos',
        ],
        'allocation' => [
            'create' => 'Añadida :allocation al servidor',
            'notes' => 'Notas de :allocation actualizadas de ":old" a ":new"',
            'primary' => 'Establecida :allocation como asignación principal',
            'delete' => 'Eliminada asignación :allocation',
        ],
        'schedule' => [
            'create' => 'Creada programación :name',
            'update' => 'Actualizada programación :name',
            'execute' => 'Ejecutada manualmente la programación :name',
            'delete' => 'Eliminada programación :name',
        ],
        'task' => [
            'create' => 'Creada tarea ":action" para la programación :name',
            'update' => 'Actualizada tarea ":action" de la programación :name',
            'delete' => 'Eliminada tarea de la programación :name',
        ],
        'settings' => [
            'rename' => 'Servidor renombrado de :old a :new',
            'description' => 'Descripción del servidor cambiada de :old a :new',
        ],
        'startup' => [
            'edit' => 'Variable :variable cambiada de ":old" a ":new"',
            'image' => 'Imagen Docker del servidor actualizada de :old a :new',
        ],
        'subuser' => [
            'create' => 'Añadido :email como subusuario',
            'update' => 'Permisos del subusuario :email actualizados',
            'delete' => 'Eliminado :email como subusuario',
        ],
    ],
];

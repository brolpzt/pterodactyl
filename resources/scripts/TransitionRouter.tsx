import React from 'react';

/**
 * Troca de sub-rotas do servidor sem desmontar o layout inteiro.
 * Evita flash/spinner ao navegar entre abas (Console, Arquivos, etc.).
 */
const TransitionRouter: React.FC = ({ children }) => <>{children}</>;

export default TransitionRouter;

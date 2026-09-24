<?php
/**
 * includes/PublicoTokenService.php
 *
 * Agendamento público por link (sem login) — Publico/paginas/agendar.php e
 * Publico/scripts/*. Centraliza a geração e a resolução do token que
 * identifica um barbeiro na URL pública, para nunca expor o id_barbeiro
 * real (sequencial, fácil de adivinhar/tentar em sequência) em nenhum
 * lugar visível ao público.
 *
 * Formato do link: <slug-do-nome>-<16 hex aleatórios>, ex.:
 *   barbearia-do-renatinho-a1b2c3d4e5f6a7b8
 * O slug existe só para o link ficar legível/reconhecível; quem garante
 * que ele não pode ser adivinhado ou testado em sequência é a parte
 * aleatória (8 bytes = 2^64 combinações possíveis), sempre comparada por
 * igualdade exata no banco (índice UNIQUE em Barbeiro.link_publico).
 */

class PublicoTokenService
{
    /**
     * Gera um novo link público único para o barbeiro informado e grava em
     * Barbeiro.link_publico. Sobrescreve um link anterior, se houver —
     * invalidando-o de propósito: quem tiver o link antigo salvo passa a
     * receber "link não encontrado" (útil se o link antigo vazar).
     */
    public static function gerarNovoLink(PDO $pdo, int $idBarbeiro, string $nomeBarbeiro): string
    {
        $slug = self::slugificar($nomeBarbeiro);

        // Tenta algumas vezes só por precaução — colisão do token aleatório
        // de 8 bytes é praticamente impossível; o UNIQUE do banco é quem
        // realmente garante que nunca existam dois links iguais.
        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $token = $slug . '-' . bin2hex(random_bytes(8));

            $stmt = $pdo->prepare('UPDATE Barbeiro SET link_publico = :link WHERE id_barbeiro = :id');
            try {
                $stmt->execute(['link' => $token, 'id' => $idBarbeiro]);
                return $token;
            } catch (PDOException $e) {
                // 23000 = violação de UNIQUE (colisão improvável) — tenta de novo.
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Não foi possível gerar um link público único.');
    }

    /**
     * Resolve um token da URL pública para os dados básicos (e não
     * sensíveis) do barbeiro. Retorna null se o token não existir ou tiver
     * formato inválido — tratado pela página/endpoints públicos como
     * "link inválido ou desativado".
     *
     * @return array{id_barbeiro:int, nome:string, foto:?string}|null
     */
    public static function resolverBarbeiro(PDO $pdo, string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-z0-9-]{3,160}$/', $token)) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id_barbeiro, nome, foto FROM Barbeiro WHERE link_publico = :token');
        $stmt->execute(['token' => $token]);
        $barbeiro = $stmt->fetch();

        if (!$barbeiro) {
            return null;
        }

        return [
            'id_barbeiro' => (int) $barbeiro['id_barbeiro'],
            'nome'        => $barbeiro['nome'],
            'foto'        => $barbeiro['foto'],
        ];
    }

    /**
     * Garante que o barbeiro tenha um link público, gerando um na hora (e
     * já gravando no banco) se ele ainda não tiver nenhum — usado pela
     * página de "escolha seu barbeiro" (Publico/paginas/escolher_barbeiro.php),
     * pra listar todos os barbeiros da barbearia sem exigir que cada um
     * tenha ido em Configurações gerar o link pessoal manualmente antes.
     */
    public static function garantirLink(PDO $pdo, int $idBarbeiro, string $nome, ?string $linkAtual): string
    {
        if ($linkAtual !== null && $linkAtual !== '') {
            return $linkAtual;
        }

        return self::gerarNovoLink($pdo, $idBarbeiro, $nome);
    }

    private static function slugificar(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
        $texto = $convertido !== false ? $convertido : $texto;
        $texto = strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
        $texto = trim($texto, '-');

        return $texto !== '' ? $texto : 'barbearia';
    }
}

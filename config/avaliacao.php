<?php

return [
    'limite_avaliacoes' => 2,
    'nota_maxima' => 10,
    'nota_corte' => 7,
    'categorias' => [
        'Pesquisador Inovador' => [
            'codigo' => 1,
            'criterios' => [
                'c1' => [
                    'nome' => 'Relevância / Originalidade',
                    'maximo' => 3,
                    'descricao' => 'Ineditismo da investigação frente ao estado da arte; relevância do problema investigado; contribuição conceitual para a fronteira do conhecimento e desafios locais de Guarapuava.',
                ],
                'c2' => [
                    'nome' => 'Metodologia',
                    'maximo' => 2,
                    'descricao' => 'Rigor técnico-científico do delineamento experimental ou teórico; clareza nas hipóteses; métodos de coleta/análise; conformidade com normas éticas (CEP/CEUA).',
                ],
                'c3' => [
                    'nome' => 'Resultados',
                    'maximo' => 3,
                    'descricao' => 'Solidez e consistência dos achados; validade dos dados estatísticos, protótipos, patentes ou publicações; coerência entre dados obtidos e conclusões.',
                ],
                'c4' => [
                    'nome' => 'Potencial de Aplicação',
                    'maximo' => 2,
                    'descricao' => 'Capacidade de transferência de tecnologia para mercado ou setor público local; facilidade de implementação em Guarapuava e desdobramentos práticos.',
                ],
            ],
            'bonus_nome' => 'Bônus de Parcerias',
            'bonus_maximo' => 0,
            'bonus_descricao' => 'Critério de bônus exclusivo da categoria Projeto Inovador (Não aplicável a Pesquisador Inovador).',
        ],
        'Professor Inovador' => [
            'codigo' => 2,
            'criterios' => [
                'c1' => [
                    'nome' => 'Criatividade / Inovação',
                    'maximo' => 3,
                    'descricao' => 'Originalidade da prática pedagógica frente às rotinas tradicionais; pioneirismo em atividades lúdicas, interdisciplinares ou dinâmicas no ambiente escolar.',
                ],
                'c2' => [
                    'nome' => 'Impacto na Aprendizagem',
                    'maximo' => 3,
                    'descricao' => 'Evidências concretas de melhoria da aprendizagem, engajamento e protagonismo; desenvolvimento socioemocional/cognitivo; mitigação de evasão e sucesso escolar.',
                ],
                'c3' => [
                    'nome' => 'Replicabilidade',
                    'maximo' => 2,
                    'descricao' => 'Viabilidade técnica, operacional e orçamentária para que a prática seja transferida, adaptada e adotada por outros educadores ou escolas de Guarapuava.',
                ],
                'c4' => [
                    'nome' => 'Tecnologias / Metodologias',
                    'maximo' => 2,
                    'descricao' => 'Uso estruturado de metodologias ativas (sala invertida, gamificação, cultura maker, PBL) e integração de recursos tecnológicos ao processo pedagógico.',
                ],
            ],
            'bonus_nome' => 'Bônus de Parcerias',
            'bonus_maximo' => 0,
            'bonus_descricao' => 'Critério de bônus exclusivo da categoria Projeto Inovador (Não aplicável a Professor Inovador).',
        ],
        'Projeto Inovador' => [
            'codigo' => 3,
            'criterios' => [
                'c1' => [
                    'nome' => 'Grau de Inovação',
                    'maximo' => 3,
                    'descricao' => 'Nível de novidade (incremental ou disruptiva) do produto, processo ou serviço frente ao mercado e contexto socioeconômico regional; tecnologia própria/modelo novo.',
                ],
                'c2' => [
                    'nome' => 'Impacto para Guarapuava',
                    'maximo' => 3,
                    'descricao' => 'Benefícios socioeconômicos ou ambientais gerados para Guarapuava: empregos qualificados, atração de investimentos, digitalização de serviços e conexão do ecossistema.',
                ],
                'c3' => [
                    'nome' => 'Viabilidade / Maturidade',
                    'maximo' => 2,
                    'descricao' => 'Estágio de maturação (TRL, protótipo funcional, produto no mercado); robustez do modelo de governança, sustentabilidade econômico-financeira e capacidade de execução.',
                ],
                'c4' => [
                    'nome' => 'Qualidade da Apresentação',
                    'maximo' => 2,
                    'descricao' => 'Clareza, coerência lógica e qualidade da escrita técnica do Anexo I; apresentação de indicadores de desempenho sólidos; dados e anexos fundamentados.',
                ],
            ],
            'bonus_nome' => 'Bônus de Parcerias',
            'bonus_maximo' => 0.5,
            'bonus_descricao' => 'Parcerias comprovadas com o ecossistema de C,T&I de Guarapuava (Art. 15, § 2º, IV). Pontuação adicional de até +0,5 ponto, respeitado o teto de 10,0.',
        ],
        'Mulher Inovadora' => [
            'codigo' => 4,
            'criterios' => [
                'c1' => [
                    'nome' => 'Grau de Inovação',
                    'maximo' => 3,
                    'descricao' => 'Originalidade, sofisticação da solução proposta e diferenciais competitivos do modelo, projeto ou tecnologia desenvolvida sob liderança feminina.',
                ],
                'c2' => [
                    'nome' => 'Impacto da Inovação',
                    'maximo' => 3,
                    'descricao' => 'Resultados mensuráveis sob liderança feminina; equidade de gênero em C,T&I; benefícios sociais, econômicos, educacionais ou ambientais entregues ao município.',
                ],
                'c3' => [
                    'nome' => 'Viabilidade / Maturidade',
                    'maximo' => 2,
                    'descricao' => 'Nível de prontidão da iniciativa em Guarapuava; robustez das operações, escalabilidade e capacidade de tração da gestão liderada pela proponente.',
                ],
                'c4' => [
                    'nome' => 'Qualidade da Apresentação',
                    'maximo' => 2,
                    'descricao' => 'Estruturação e rigor técnico no Resumo Expandido (Anexo I); consistência entre problema, metodologia e resultados; evidências objetivas e clareza.',
                ],
            ],
            'bonus_nome' => 'Bônus de Parcerias',
            'bonus_maximo' => 0,
            'bonus_descricao' => 'Critério de bônus exclusivo da categoria Projeto Inovador (Não aplicável a Mulher Inovadora).',
        ],
    ],
];

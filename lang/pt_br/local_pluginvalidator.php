<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Pt_br Lang
 *
 * @package     local_pluginvalidator
 * @copyright   2026 Eduardo Kraus
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['back'] = 'Voltar';
$string['diagnosticdebuginfo'] = 'Informações de depuração do Moodle';
$string['diagnosticexceptionclass'] = 'Classe da exceção';
$string['diagnosticexceptiondata'] = 'Dados da exceção do Moodle';
$string['diagnosticexceptionfile'] = 'Arquivo da exceção';
$string['diagnosticexceptionline'] = 'Linha da exceção';
$string['engine'] = 'Motor de validação';
$string['engine_execution'] = 'Validação de execução';
$string['engine_execution_desc'] = 'Executa testes reais e validações de contrato dentro do Moodle, incluindo backup/restauração, callbacks de lib.php, tarefas, eventos, hooks, configurações administrativas, privacidade, blocos, filtros, File API, notas, conclusão e smoke tests por tipo de plugin.';
$string['engine_moodlepluginvalidate'] = 'Moodle Plugin Validate';
$string['engine_moodlepluginvalidate_desc'] = 'Validação estruturada do projeto EduardoKrausME/moodle-plugin-validate, com regras, checks, arquivos e linhas separados.';
$string['engineavailable'] = 'Motor de validação disponível';
$string['enginebuiltin'] = 'Interno';
$string['enginebundlednoupdate'] = 'Este motor acompanha o plugin. Atualize o pacote do plugin para atualizar o motor.';
$string['engineinstalled'] = 'Motor de validação {$a} instalado com sucesso.';
$string['engineinstallfailed'] = 'Não foi possível instalar o motor de validação: {$a}';
$string['enginemissing'] = 'O motor de validação ainda não está instalado.';
$string['enginenotinstalled'] = 'Instale o motor de validação antes de executar a validação.';
$string['enginephpversion'] = 'O motor Moodle Plugin Validate integrado requer PHP 8.1 ou superior.';
$string['enginesourcebundled'] = '(embutido)';
$string['enginesourcedownloaded'] = '(baixado)';
$string['executionbackuperror'] = 'A execução do backup falhou: {$a}';
$string['executionbackupnoinstance'] = 'A pasta backup/moodle2 existe, mas nenhuma instância instalada da atividade foi encontrada para executar um backup real.';
$string['executionbackupok'] = 'Backup executado com sucesso para o módulo de curso {$a}.';
$string['executionbackupunsupported'] = 'A pasta backup/moodle2 existe, mas a execução completa do backup atualmente é suportada apenas para módulos de atividade.';
$string['executionmodformclassinvalid'] = 'A classe de formulário {$a} não estende moodleform_mod.';
$string['executionmodformclassmissing'] = 'A classe de formulário esperada {$a} não foi encontrada.';
$string['executionmodformerror'] = 'O mod_form.php falhou durante a instanciação em tempo de execução: {$a}';
$string['executionmodformmissing'] = 'O plugin de atividade não contém mod_form.php.';
$string['executionmodformok'] = 'O mod_form.php foi carregado, instanciado e preenchido com sucesso.';
$string['executionnotapplicable'] = 'Nenhum teste de execução se aplica a este plugin.';
$string['executionservicecontractonly'] = 'A função externa {$a->name} possui contrato válido, mas a execução real foi ignorada: {$a->reason}';
$string['executionserviceerror'] = 'A função externa {$a->name} falhou na validação do contrato em tempo de execução: {$a->message}';
$string['executionserviceexecuted'] = 'A função externa {$a} foi executada com sucesso e retornou um valor aceito pelo contrato de retorno declarado.';
$string['executionserviceinvalid'] = 'A função externa {$a} possui uma definição inválida.';
$string['executionserviceok'] = 'A função externa {$a} foi carregada com sucesso e seus contratos de parâmetros e retorno são válidos.';
$string['executionservicesempty'] = 'O db/services.php existe, mas não define funções externas.';
$string['executionserviceskipparams'] = 'a função exige parâmetros reais de entrada';
$string['executionserviceskipwrite'] = 'a função não está explicitamente declarada como somente leitura';
$string['executionservicesloaderror'] = 'Não foi possível carregar db/services.php: {$a}';
$string['executionserviceunregistered'] = 'A função externa {$a} está declarada em db/services.php, mas não está registrada no Moodle.';
$string['executionservicewrongcomponent'] = 'A função externa {$a} está registrada para outro componente.';
$string['executionstatecontract'] = 'Contrato validado';
$string['executionstateexecuted'] = 'Executado';
$string['executionstatenotapplicable'] = 'Não aplicável';
$string['failed'] = 'Falhou';
$string['installengine'] = 'Instalar motor';
$string['invalidengine'] = 'O motor de validação selecionado não existe.';
$string['invalidplugin'] = 'O plugin selecionado não existe ou é um plugin nativo do Moodle.';
$string['nosearchresults'] = 'Nenhum plugin de terceiros foi encontrado para essa busca.';
$string['nothirdpartyplugins'] = 'Nenhum plugin de terceiros foi encontrado.';
$string['passed'] = 'Aprovado';
$string['pluginname'] = 'Validador de plugins';
$string['privacy:metadata'] = 'O Validador de plugins não armazena dados pessoais.';
$string['release'] = 'Release';
$string['result'] = 'Resultado';
$string['runtimecheck01'] = 'Falha ao carregar lib.php: {$a->p1}';
$string['runtimecheck02'] = '{$a->p1}() foi carregada e seu contrato de chamada é válido; a execução foi ignorada intencionalmente.';
$string['runtimecheck03'] = '{$a->p1}() foi executada com constantes de recursos padrão sem erros.';
$string['runtimecheck04'] = '{$a->p1}() pode ser chamada, mas não existe uma instância instalada do módulo para uma execução segura.';
$string['runtimecheck05'] = '{$a->p1}() foi executada com o módulo de curso {$a->p2}.';
$string['runtimecheck06'] = '{$a->p1}() foi executada e retornou {$a->p2} área(s) de arquivos.';
$string['runtimecheck07'] = 'Falha ao executar {$a->p1}(): {$a->p2}';
$string['runtimecheck08'] = 'lib.php foi carregado, mas não declara os callbacks padrão verificados por este validador.';
$string['runtimecheck09'] = 'O ciclo de criação, leitura e exclusão de arquivos pela File API foi concluído na área \'{$a->p1}\'.';
$string['runtimecheck10'] = 'Falha no ciclo de operações da File API: {$a->p1}';
$string['runtimecheck11'] = 'backup/moodle2 existe, mas a execução automática de backup e restauração está limitada aos módulos de atividade.';
$string['runtimecheck12'] = 'As classes de backup e restauração existem, mas não há uma instância instalada de atividade para executar o ciclo completo.';
$string['runtimecheck13'] = 'O ciclo de backup e restauração foi concluído do cmid {$a->p1} para o cmid temporário {$a->p2}.';
$string['runtimecheck14'] = 'Falha no ciclo de backup e restauração: {$a->p1}';
$string['runtimecheck15'] = '{$a->p1} foi carregada e get_name() executado; execute() não foi chamado intencionalmente.';
$string['runtimecheck16'] = 'Falha ao validar a tarefa agendada: {$a->p1}';
$string['runtimecheck17'] = '{$a->p1} foi instanciada e seus dados personalizados serializados; execute() não foi chamado intencionalmente.';
$string['runtimecheck18'] = 'Falha ao validar a tarefa adhoc {$a->p1}: {$a->p2}';
$string['runtimecheck19'] = 'Nenhuma classe que estende core\\task\\adhoc_task foi encontrada.';
$string['runtimecheck20'] = 'O observador do evento {$a->p1} possui um callback válido que aceita um objeto de evento; o disparo foi ignorado.';
$string['runtimecheck21'] = 'Falha ao validar o observador de eventos: {$a->p1}';
$string['runtimecheck22'] = 'O hook {$a->p1} e o callback {$a->p2} podem ser carregados; o disparo foi ignorado.';
$string['runtimecheck23'] = 'Falha ao validar o hook: {$a->p1}';
$string['runtimecheck24'] = 'settings.php foi carregado com sucesso durante a reconstrução da árvore de administração do Moodle.';
$string['runtimecheck25'] = 'Falha ao carregar settings.php durante a reconstrução da árvore de administração: {$a->p1}';
$string['runtimecheck26'] = '{$a->p1}::get_metadata() foi executado com sucesso.';
$string['runtimecheck27'] = '{$a->p1} declara corretamente core_privacy\\local\\metadata\\null_provider.';
$string['runtimecheck28'] = 'Falha ao validar o provedor de privacidade: {$a->p1}';
$string['runtimecheck29'] = '{$a->p1}::init() e applicable_formats() foram executados com sucesso.';
$string['runtimecheck30'] = '{$a->p1}::get_content() foi executado usando a instância de bloco {$a->p2}.';
$string['runtimecheck31'] = 'get_content() pode ser chamado, mas não existe uma instância de bloco instalada para uma execução realista.';
$string['runtimecheck32'] = 'Falha na validação do bloco em tempo de execução: {$a->p1}';
$string['runtimecheck33'] = '{$a->p1}::filter() foi executado com sucesso.';
$string['runtimecheck34'] = 'Falha na validação do filtro em tempo de execução: {$a->p1}';
$string['runtimecheck35'] = 'FEATURE_GRADE_HAS_GRADE está declarado, mas grade_item_update() está ausente.';
$string['runtimecheck36'] = '{$a->p1}() pode ser chamada, mas não existe uma instância instalada para uma execução segura.';
$string['runtimecheck37'] = '{$a->p1}() foi executada com sucesso para a instância {$a->p2}.';
$string['runtimecheck38'] = '{$a->p1}() foi executada para o usuário com notas existente {$a->p2}.';
$string['runtimecheck39'] = '{$a->p1}() é válida, mas nenhum usuário com notas foi encontrado; a sincronização em massa foi ignorada.';
$string['runtimecheck40'] = 'Falha na validação da API de notas em tempo de execução: {$a->p1}';
$string['runtimecheck41'] = 'FEATURE_COMPLETION_HAS_RULES está declarado, mas não existe a classe moderna custom_completion nem o callback legado get_completion_state().';
$string['runtimecheck42'] = 'As classes e callbacks da API de conclusão podem ser carregados, mas não existe uma instância instalada para avaliação em tempo de execução.';
$string['runtimecheck43'] = '{$a->p1} foi carregada; {$a->p2} regra(s) personalizadas foram identificadas e {$a->p3} regra(s) habilitadas foram avaliadas.';
$string['runtimecheck44'] = '{$a->p1}() foi executada com sucesso.';
$string['runtimecheck45'] = 'Falha na validação da API de conclusão em tempo de execução: {$a->p1}';
$string['runtimecheck46'] = 'O renderizador personalizado foi instanciado com sucesso pelo Moodle.';
$string['runtimecheck47'] = 'Falha ao validar o renderizador: {$a->p1}';
$string['runtimecheck48'] = 'A classe do formato de curso está instalada, mas nenhum curso a utiliza no momento.';
$string['runtimecheck49'] = 'O formato de curso carregou o curso {$a->p1} e identificou a seção 0 com sucesso.';
$string['runtimecheck50'] = 'Falha na validação do formato de curso em tempo de execução: {$a->p1}';
$string['runtimecheck51'] = '{$a->p1} foi instanciada e seus callbacks básicos de metadados foram executados.';
$string['runtimecheck52'] = 'Falha ao validar o plugin de autenticação: {$a->p1}';
$string['runtimecheck53'] = 'A classe do plugin de inscrição foi carregada; operações de adicionar ou excluir instâncias foram ignoradas intencionalmente.';
$string['runtimecheck54'] = 'Falha ao validar o plugin de inscrição: {$a->p1}';
$string['runtimecheck55'] = '{$a->p1} foi carregada com herança válida de repositório; a listagem remota foi ignorada intencionalmente.';
$string['runtimecheck56'] = 'Falha ao validar o plugin de repositório: {$a->p1}';
$string['runtimecheck57'] = 'O tipo de questão foi instanciado pelo question_bank e menu_name() foi executado.';
$string['runtimecheck58'] = 'Falha ao validar o tipo de questão: {$a->p1}';
$string['runtimeeventdiag01'] = 'Plugin em validação';
$string['runtimeeventdiag02'] = 'Declaração do observador';
$string['runtimeeventdiag03'] = 'Observador';
$string['runtimeeventdiag04'] = 'Evento observado';
$string['runtimeeventdiag05'] = 'Componente do evento';
$string['runtimeeventdiag06'] = 'Callback';
$string['runtimeeventdiag07'] = 'Observador de outro componente';
$string['runtimeeventdiag08'] = 'Componente do evento instalado';
$string['runtimeeventdiag09'] = 'Diretório do componente do evento';
$string['runtimeeventdiag10'] = 'Carregador automático do Moodle identificou o evento';
$string['runtimeeventdiag11'] = 'Arquivo esperado da classe de evento';
$string['runtimeeventdiag12'] = 'Arquivo da classe de evento existe';
$string['runtimeeventdiag13'] = 'Carregamento direto alternativo do evento';
$string['runtimeeventdiag14'] = 'Classe do evento existe';
$string['runtimeeventdiag15'] = 'Estende core\\event\\base';
$string['runtimeeventexternal'] = ' O evento pertence ao componente externo \'{$a}\'.';
$string['runtimeeventmissing'] = 'Ao validar {$a->component}, o observador #{$a->position} em db/events.php referencia o evento \'{$a->event}\', mas não é possível carregar a classe do evento.';
$string['runtimeexception01'] = 'O callback não foi identificado como código do plugin.';
$string['runtimeexception02'] = 'get_file_areas() deve retornar um array.';
$string['runtimeexception03'] = 'O ciclo de operações da File API não retornou o conteúdo gravado.';
$string['runtimeexception04'] = 'O backup não gerou o arquivo backup_destination.';
$string['runtimeexception05'] = 'A pré-verificação da restauração retornou falso.';
$string['runtimeexception06'] = 'Não foi possível identificar o módulo de curso restaurado.';
$string['runtimeexception07'] = 'db/tasks.php não declara nenhuma tarefa agendada.';
$string['runtimeexception08'] = 'A classe de tarefa agendada \'{$a->p1}\' não pôde ser carregada.';
$string['runtimeexception09'] = '{$a->p1} não estende core\\task\\scheduled_task.';
$string['runtimeexception10'] = '{$a->p1}::get_name() retornou um valor vazio.';
$string['runtimeexception11'] = '{$a->p1}::execute() deve ser público e não receber parâmetros.';
$string['runtimeexception12'] = 'Falha no ciclo de serialização dos dados personalizados.';
$string['runtimeexception13'] = 'execute() deve ser público e não receber parâmetros.';
$string['runtimeexception14'] = 'db/events.php não declara observadores.';
$string['runtimeexception15'] = 'O observador #{$a->p1} em db/events.php não declara um eventname válido.';
$string['runtimeexception17'] = 'O evento \'{$a->p2}\' do observador #{$a->p1} existe, mas não estende core\\event\\base.';
$string['runtimeexception18'] = 'db/hooks.php não declara callbacks.';
$string['runtimeexception19'] = 'O hook \'{$a->p1}\' não pôde ser carregado nesta versão do Moodle.';
$string['runtimeexception20'] = 'admin_get_root() não retornou uma árvore de administração.';
$string['runtimeexception21'] = 'Não foi possível carregar o provedor de privacidade {$a->p1}.';
$string['runtimeexception22'] = 'get_metadata() não retornou uma coleção de metadados.';
$string['runtimeexception23'] = 'O provedor não implementa metadata_provider nem null_provider.';
$string['runtimeexception24'] = '{$a->p1} não estende block_base.';
$string['runtimeexception25'] = 'applicable_formats() deve retornar um array.';
$string['runtimeexception26'] = 'block_instance() não conseguiu criar a instância de bloco instalada.';
$string['runtimeexception27'] = 'Não foi possível carregar a classe de filtro.';
$string['runtimeexception28'] = 'filter() deve retornar uma string.';
$string['runtimeexception29'] = '{$a->p1} não estende core_completion\\activity_custom_completion.';
$string['runtimeexception30'] = 'get_defined_custom_rules() deve retornar um array.';
$string['runtimeexception31'] = 'As descrições das regras de conclusão e a ordem de classificação devem ser arrays.';
$string['runtimeexception32'] = 'A regra de conclusão disponível \'{$a->p1}\' não foi definida pelo plugin.';
$string['runtimeexception33'] = 'A fábrica de renderizadores do Moodle não retornou um renderizador.';
$string['runtimeexception34'] = '{$a->p1} não estende auth_plugin_base.';
$string['runtimeexception35'] = 'enrol_get_plugin() retornou falso.';
$string['runtimeexception36'] = '{$a->p1} não estende repository.';
$string['runtimeexception37'] = 'question_bank::get_qtype() não retornou um tipo de questão.';
$string['runtimeexception38'] = 'O callback \'{$a->p1}\' não pode ser chamado.';
$string['runtimeexception39'] = 'O método do callback deve ser público.';
$string['runtimeexception40'] = 'O callback deve aceitar pelo menos {$a->p1} parâmetro(s).';
$string['runtimestate01'] = 'Sim';
$string['runtimestate02'] = 'Não';
$string['runtimestate03'] = 'Desconhecido';
$string['runtimestate04'] = 'Não verificado';
$string['runtimestate05'] = 'Não tentado';
$string['runtimestate06'] = '(desconhecido)';
$string['runtimestate07'] = '(vazio)';
$string['runtimestate08'] = '(não identificado)';
$string['runvalidation'] = 'Executar validação';
$string['search'] = 'Buscar';
$string['searchplaceholder'] = 'Nome, componente ou tipo do plugin';
$string['searchplugins'] = 'Buscar plugins';
$string['searchresultsfor'] = 'Resultados para "{$a}"';
$string['stacktrace'] = 'Stack trace';
$string['statuserror'] = 'Erro';
$string['statusok'] = 'OK';
$string['statuswarning'] = 'Aviso';
$string['summaryerrors'] = 'Erros';
$string['summaryok'] = 'OK';
$string['summarywarnings'] = 'Avisos';
$string['technicaldetails'] = 'Detalhes técnicos';
$string['thirdpartyonly'] = 'Somente plugins de extensão instalados são exibidos. Plugins nativos do Moodle são excluídos automaticamente.';
$string['updateengine'] = 'Atualizar motor';
$string['validationruntimeerror'] = 'Erro ao executar o validador';
$string['validations'] = 'Validações';
$string['validator_desc'] = 'Executa diretamente a biblioteca EduardoKrausME/moodle-plugin-validate contra este plugin instalado.';
$string['version'] = 'Versão';
$string['welcome'] = 'Validador de plugins';
$string['welcome_desc'] = 'Selecione um tipo de plugin para visualizar os plugins de terceiros instalados. Plugins nativos do Moodle são ocultados automaticamente.';

<div class="eg-stats-row" id="cards-totais">
  <x-ui.stat-card color="purple" icon="people-fill" label="Submissões" hint="Respostas completas registradas">
    <x-slot:value data-total="submissoes">0</x-slot:value>
  </x-ui.stat-card>
  <x-ui.stat-card color="blue" icon="list-check" label="Questões" hint="Com alguma resposta">
    <x-slot:value data-total="questoes">0</x-slot:value>
  </x-ui.stat-card>
  <x-ui.stat-card color="green" icon="calendar-event-fill" label="Eventos" hint="Com respostas vinculadas">
    <x-slot:value data-total="eventos">0</x-slot:value>
  </x-ui.stat-card>
  <x-ui.stat-card color="orange" icon="clock-history" label="Última resposta" hint="Horário da última entrada" small>
    <x-slot:value data-total="ultima">-</x-slot:value>
  </x-ui.stat-card>
</div>

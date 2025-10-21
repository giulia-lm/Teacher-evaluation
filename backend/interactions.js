// interactions.js
document.addEventListener('DOMContentLoaded', () => {

  const availableFormsContainer = document.getElementById('available-forms');
  const surveySection = document.getElementById('survey-section');
  const surveyForm = document.getElementById('survey-form');
  const surveyTitle = document.getElementById('survey-title');
  const questionsContainer = document.getElementById('questions-container');
  const formIdInput = document.getElementById('form_id');
  const surveyTypeInput = document.getElementById('survey_type');

  // 1️⃣ Cargar encuestas disponibles
  fetch('../../backend/get_available_forms.php')
    .then(res => res.json())
    .then(data => {
      if (!data.forms || data.forms.length === 0) {
        availableFormsContainer.innerHTML = '<p>No hay encuestas disponibles por contestar.</p>';
        return;
      }

      data.forms.forEach(f => {
        if(f.answered) return; // ya contestada

        const card = document.createElement('div');
        card.className = 'form-card';
        card.dataset.id = f.id;
        card.dataset.title = f.title;
        card.dataset.type = f.id_docente ? 'docente' : 'materia';

        card.innerHTML = `
          <h3>${f.title}</h3>
          <p>Materia: ${f.id_materia}</p>
          <button class="btn-open-form">Responder</button>
        `;

        availableFormsContainer.appendChild(card);

        // Abrir encuesta al dar click
        card.querySelector('.btn-open-form').addEventListener('click', () => {
          formIdInput.value = f.id;
          surveyTypeInput.value = f.id_docente ? 'docente' : 'materia';
          surveyTitle.textContent = f.title;

          // Renderizar preguntas (ejemplo temporal)
          questionsContainer.innerHTML = `
            <label class="question">1. El contenido fue claro y organizado.</label>
            <div class="options">
              <input type="radio" name="q1" value="1" required> Muy en desacuerdo
              <input type="radio" name="q1" value="2"> En desacuerdo
              <input type="radio" name="q1" value="3"> Neutral
              <input type="radio" name="q1" value="4"> De acuerdo
              <input type="radio" name="q1" value="5"> Muy de acuerdo
            </div>

            <label class="question">2. La carga de trabajo fue adecuada.</label>
            <div class="options">
              <input type="radio" name="q2" value="1" required> Muy en desacuerdo
              <input type="radio" name="q2" value="2"> En desacuerdo
              <input type="radio" name="q2" value="3"> Neutral
              <input type="radio" name="q2" value="4"> De acuerdo
              <input type="radio" name="q2" value="5"> Muy de acuerdo
            </div>

            <label class="question">3. Comentarios adicionales:</label>
            <textarea name="comments" rows="4" placeholder="Escribe tu opinión..."></textarea>
          `;

          surveySection.style.display = 'block';
          surveySection.scrollIntoView({ behavior: 'smooth' });
        });
      });
    })
    .catch(err => {
      console.error('Error al cargar encuestas:', err);
      availableFormsContainer.innerHTML = '<p>Error al cargar encuestas.</p>';
    });

});

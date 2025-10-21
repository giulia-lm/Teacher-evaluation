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
        if (f.answered) return; // ya contestada

        const card = document.createElement('div');
        card.className = 'form-card';
        card.dataset.id = f.id;
        card.dataset.type = f.id_docente ? 'docente' : 'materia';
        card.innerHTML = `
          <h3>${f.title}</h3>
          <p>${f.id_docente ? 'Docente: ' + f.docente : 'Materia: ' + f.materia}</p>
          <button class="btn-open-form">Responder</button>
        `;
        availableFormsContainer.appendChild(card);

        // Abrir encuesta al hacer click
        card.querySelector('.btn-open-form').addEventListener('click', () => {
          formIdInput.value = f.id;
          surveyTypeInput.value = f.id_docente ? 'docente' : 'materia';
          surveyTitle.textContent = f.title;
          questionsContainer.innerHTML = '';

          // Pedir las preguntas del form por separado
          fetch(`../../backend/get_form_questions.php?form_id=${f.id}`)
            .then(res => res.json())
            .then(qdata => {
              qdata.questions.forEach(q => {
                let html = `<div class="question-block">
                              <label class="question">${q.text}</label><br>`;
                if(q.type === 'multiple') {
                  q.choices.forEach(c => {
                    html += `<input type="radio" name="choice_${q.id}" value="${c.id}" required> ${c.text}<br>`;
                  });
                } else if(q.type === 'texto') {
                  html += `<textarea name="q${q.id}" rows="3" placeholder="Escribe tu respuesta..."></textarea>`;
                }
                html += `</div><br>`;
                questionsContainer.innerHTML += html;
              });

              surveySection.style.display = 'block';
              surveySection.scrollIntoView({ behavior: 'smooth' });
            })
            .catch(err => console.error('Error al cargar preguntas:', err));
        });
      });
    })
    .catch(err => {
      console.error('Error al cargar encuestas:', err);
      availableFormsContainer.innerHTML = '<p>Error al cargar encuestas.</p>';
    });
});

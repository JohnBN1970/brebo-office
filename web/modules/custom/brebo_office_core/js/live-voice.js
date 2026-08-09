(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.breboLiveVoice = {
    attach(context) {
      once('brebo-live-voice', '.brebo-live-recorder', context).forEach((room) => {
        const form = room.closest('form');
        const start = room.querySelector('.brebo-live-recorder__start');
        const stop = room.querySelector('.brebo-live-recorder__stop');
        const status = room.querySelector('.brebo-live-recorder__status');
        const timer = room.querySelector('.brebo-live-recorder__time');
        const meter = room.querySelector('.brebo-live-recorder__meter span');
        const preview = room.querySelector('.brebo-live-recorder__preview');
        let recorder;
        let stream;
        let chunks = [];
        let startedAt = 0;
        let clock;
        let animation;
        let audioContext;

        const formatTime = (milliseconds) => {
          const seconds = Math.floor(milliseconds / 1000);
          const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
          const minutes = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
          return hours + ':' + minutes + ':' + String(seconds % 60).padStart(2, '0');
        };

        const setStatus = (message, state) => {
          status.textContent = message;
          room.dataset.state = state || 'idle';
        };

        const stopTracks = () => {
          if (stream) {
            stream.getTracks().forEach((track) => track.stop());
          }
          if (audioContext) {
            audioContext.close();
          }
          cancelAnimationFrame(animation);
          clearInterval(clock);
          meter.style.width = '0%';
        };

        const showLevel = () => {
          if (!stream) return;
          audioContext = new (window.AudioContext || window.webkitAudioContext)();
          const analyser = audioContext.createAnalyser();
          const source = audioContext.createMediaStreamSource(stream);
          const values = new Uint8Array(analyser.frequencyBinCount);
          source.connect(analyser);
          const draw = () => {
            analyser.getByteFrequencyData(values);
            const average = values.reduce((sum, value) => sum + value, 0) / values.length;
            meter.style.width = Math.min(100, average * 1.8) + '%';
            animation = requestAnimationFrame(draw);
          };
          draw();
        };

        const attachRecording = (blob) => {
          const input = form.querySelector('input[type="file"][name*="recording"]');
          if (!input || typeof DataTransfer === 'undefined') {
            setStatus('Opname gereed. Automatisch klaarzetten wordt niet door deze browser ondersteund.', 'ready');
            return;
          }
          const file = new File([blob], 'brebo-live-' + new Date().toISOString().replace(/[:.]/g, '-') + '.webm', {
            type: blob.type || 'audio/webm',
          });
          const transfer = new DataTransfer();
          transfer.items.add(file);
          input.files = transfer.files;
          input.dispatchEvent(new Event('change', { bubbles: true }));
          preview.src = URL.createObjectURL(blob);
          preview.hidden = false;
          const upload = input.closest('.js-form-managed-file')?.querySelector('input[type="submit"]');
          if (upload) {
            setStatus('Opname gereed; bronbestand wordt privé klaargezet…', 'processing');
            upload.click();
          }
          else {
            setStatus('Opname gereed. Sla het formulier op om de bron te registreren.', 'ready');
          }
        };

        start.addEventListener('click', async (event) => {
          event.preventDefault();
          try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const preferred = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus']
              .find((type) => window.MediaRecorder.isTypeSupported(type));
            recorder = new MediaRecorder(stream, preferred ? { mimeType: preferred } : undefined);
            chunks = [];
            recorder.addEventListener('dataavailable', (dataEvent) => {
              if (dataEvent.data.size > 0) chunks.push(dataEvent.data);
            });
            recorder.addEventListener('stop', () => {
              const blob = new Blob(chunks, { type: recorder.mimeType || 'audio/webm' });
              stopTracks();
              attachRecording(blob);
            });
            recorder.start(1000);
            startedAt = Date.now();
            timer.textContent = '00:00:00';
            clock = setInterval(() => {
              timer.textContent = formatTime(Date.now() - startedAt);
            }, 250);
            showLevel();
            start.disabled = true;
            stop.disabled = false;
            setStatus('Live opname actief. Het originele geluid blijft lokaal tot de opname wordt gestopt.', 'recording');
          }
          catch (error) {
            setStatus('Microfoontoegang is niet beschikbaar: ' + error.message, 'error');
          }
        });

        stop.addEventListener('click', (event) => {
          event.preventDefault();
          if (recorder && recorder.state !== 'inactive') recorder.stop();
          start.disabled = false;
          stop.disabled = true;
        });

        window.addEventListener('beforeunload', () => {
          if (recorder && recorder.state === 'recording') recorder.stop();
          stopTracks();
        });
      });
    },
  };
})(Drupal, once);

import {
  Button,
  FormControl,
  FormLabel,
  Input,
  Textarea,
  VStack,
  Text,
  Select,
  HStack,
  RadioGroup,
  Radio,
} from '@calvient/decal';
import Layout from '../../../Components/Layout.tsx';
import {useForm} from '@inertiajs/react';
import {Report} from '../../../Types/Report.ts';
import {Series} from '../../../Types/Series.ts';
import {MdBarChart, MdLineAxis, MdPieChart, MdTableView} from 'react-icons/md';
import React from 'react';
import BoxSelect from '../../../Components/BoxSelect.tsx';
import AddFilters from './Components/AddFilters.tsx';
import {Section} from '../../../Types/Section.ts';

interface Props {
  report: Report;
  series: Series[];
}

const Create = ({report, series}: Props) => {
  const {data, setData, post, processing, errors} = useForm<Section>({
    name: '',
    description: '',
    series: '',
    slice: '',
    filters: [],
    format: 'table',
  });

  const selectedSeries = series.find((s) => s.name === data.series);

  const submit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    post(`/arbol/reports/${report.id}/sections`);
  };

  return (
    <form onSubmit={submit}>
      <VStack spacing={8}>
        <FormControl isRequired>
          <FormLabel>Section Type</FormLabel>
          <BoxSelect
            value={data.format}
            options={[
              {label: 'Table', icon: MdTableView, value: 'table'},
              {label: 'Line Graph', icon: MdLineAxis, value: 'line'},
              {label: 'Bar Chart', icon: MdBarChart, value: 'bar'},
              {label: 'Pie Chart', icon: MdPieChart, value: 'pie'},
            ]}
            onSelect={(format) => setData('format', format)}
          />
          {errors.format && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.format}
            </Text>
          )}
        </FormControl>

        <FormControl isRequired>
          <FormLabel>Section Name</FormLabel>
          <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
          {errors.name && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.name}
            </Text>
          )}
        </FormControl>

        <FormControl>
          <FormLabel>Section Description</FormLabel>
          <Textarea
            value={data.description ?? ''}
            onChange={(e) => setData('description', e.target.value)}
          />
          {errors.description && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.description}
            </Text>
          )}
        </FormControl>

        <FormControl isRequired>
          <FormLabel>Data Set</FormLabel>
          <Select
            placeholder={'Select a dataset'}
            value={data.series}
            onChange={(e) => setData('series', e.target.value)}
          >
            {series.map((s) => {
              return (
                <option key={s.name} value={s.name}>
                  {s.name}
                </option>
              );
            })}
          </Select>
        </FormControl>

        {data.series.length > 0 && selectedSeries && (
          <HStack w={'full'} alignItems={'flex-start'}>
            {Object.keys(selectedSeries.filters).length > 0 && (
              <AddFilters
                allFilters={selectedSeries.filters}
                selectedFilters={data.filters}
                onFiltersChange={(filters) => setData('filters', filters)}
              />
            )}

            {selectedSeries.slices.length > 0 && (
              <FormControl flex={1}>
                <FormLabel>Sub-divide the data by:</FormLabel>
                <RadioGroup onChange={(value) => setData('slice', value)} value={data.slice}>
                  <VStack w={'full'}>
                    {selectedSeries.slices.map((slice) => (
                      <Radio w={'full'} key={slice} value={slice}>
                        {slice}
                      </Radio>
                    ))}
                  </VStack>
                </RadioGroup>
              </FormControl>
            )}
          </HStack>
        )}
        <Button type={'submit'} isLoading={processing} colorScheme={'blue'}>
          Add Section to Report
        </Button>
      </VStack>
    </form>
  );
};

Create.layout = (page: React.ReactElement) => <Layout children={page} />;

export default Create;

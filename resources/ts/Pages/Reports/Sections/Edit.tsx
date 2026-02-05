import {
  Button,
  FormControl,
  FormLabel,
  Input,
  Textarea,
  VStack,
  Text,
  HStack,
  RadioGroup,
  Radio,
} from '@calvient/decal';
import Layout from '../../../Components/Layout.tsx';
import {router, useForm} from '@inertiajs/react';
import {Report} from '../../../Types/Report.ts';
import {Series} from '../../../Types/Series.ts';
import {MdBarChart, MdLineAxis, MdPieChart, MdTableView} from 'react-icons/md';
import React from 'react';
import BoxSelect from '../../../Components/BoxSelect.tsx';
import AddFilters from './Components/AddFilters.tsx';
import {Section} from '../../../Types/Section.ts';
import ConfirmableBtn from '../../../Components/ConfirmableBtn.tsx';

interface Props {
  section: Section;
  report: Report;
  series: Series;
}

const Edit = ({series, section, report}: Props) => {
  const {data, setData, put, processing, errors} = useForm<Section>(section);

  const submit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    put(`/arbol/reports/${report.id}/sections/${section.id}`);
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

        <FormControl>
          <FormLabel>Section Sequence</FormLabel>
          <Input
            type={'number'}
            min={0}
            value={data.sequence ?? ''}
            onChange={(e) => setData('sequence', Number(e.target.value))}
          />
          {errors.description && (
            <Text color={'red'} fontSize={'sm'} mt={2}>
              {errors.description}
            </Text>
          )}
        </FormControl>

        {data.series.length > 0 && series && (
          <HStack w={'full'} alignItems={'flex-start'}>
            {Object.keys(series.filters).length > 0 && (
              <AddFilters
                allFilters={series.filters}
                selectedFilters={data.filters}
                onFiltersChange={(filters) => setData('filters', filters)}
              />
            )}

            {series.slices.length > 0 && (
              <>
                {['line', 'bar', 'pie'].includes(data.format) && (
                  <>
                    <FormControl flex={1}>
                      <FormLabel>Aggregator:</FormLabel>
                      <RadioGroup
                        onChange={(value) => setData('aggregator', value)}
                        value={data.aggregator}
                      >
                        <VStack w={'full'}>
                          {series.aggregators.map((aggregator) => (
                            <Radio w={'full'} key={aggregator} value={aggregator}>
                              {aggregator}
                            </Radio>
                          ))}
                        </VStack>
                      </RadioGroup>
                    </FormControl>
                    {['line', 'bar'].includes(data.format) && (
                      <>
                        <FormControl flex={1}>
                          <FormLabel>Show on x-axis:</FormLabel>
                          <RadioGroup
                            onChange={(value) => setData('xaxis_slice', value)}
                            value={data.xaxis_slice}
                          >
                            <VStack w={'full'}>
                              {series.slices.map((slice) => (
                                <Radio w={'full'} key={slice} value={slice}>
                                  {slice}
                                </Radio>
                              ))}
                            </VStack>
                          </RadioGroup>
                        </FormControl>
                        {data.xaxis_slice && (
                          <FormControl flex={1}>
                            <FormLabel>Percentage Mode:</FormLabel>
                            <RadioGroup
                              onChange={(value) =>
                                setData('percentage_mode', value === 'none' ? null : value)
                              }
                              value={data.percentage_mode ?? 'none'}
                            >
                              <VStack w={'full'}>
                                <Radio w={'full'} value={'none'}>
                                  None
                                </Radio>
                                <Radio w={'full'} value={'xaxis_group'}>
                                  % of X-Axis Group
                                </Radio>
                                <Radio w={'full'} value={'total'}>
                                  % of Total
                                </Radio>
                              </VStack>
                            </RadioGroup>
                          </FormControl>
                        )}
                      </>
                    )}
                  </>
                )}
                <FormControl flex={1}>
                  <FormLabel>Sub-divide the data by:</FormLabel>
                  <RadioGroup onChange={(value) => setData('slice', value)} value={data.slice}>
                    <VStack w={'full'}>
                      {series.slices.map((slice) => (
                        <Radio w={'full'} key={slice} value={slice}>
                          {slice}
                        </Radio>
                      ))}
                      <Radio w={'full'} value={'None'}>
                        None
                      </Radio>
                    </VStack>
                  </RadioGroup>
                </FormControl>
              </>
            )}
          </HStack>
        )}
        <HStack w={'full'} justifyContent={'space-between'}>
          <ConfirmableBtn
            variant={'ghost'}
            colorScheme={'red'}
            onConfirm={() => router.delete(`/arbol/reports/${report.id}/sections/${section.id}`)}
          >
            Delete
          </ConfirmableBtn>
          <Button type={'submit'} isLoading={processing} colorScheme={'blue'}>
            Update Section
          </Button>
        </HStack>
      </VStack>
    </form>
  );
};

Edit.layout = (page: React.ReactElement) => <Layout children={page} />;

export default Edit;
